<?php

declare(strict_types=1);

namespace App\Services\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AnnouncementMail;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Comunicados de la plataforma: quién los ve, lecturas y correos.
 * "Administradores" de un cliente = quienes tienen users.manage en él.
 */
class AnnouncementService
{
    private const ADMIN_PERMISSION = 'users.manage';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Comunicados vigentes para $user dentro de $tenant (el suyo o, en modo
     * soporte, el del cliente). La plataforma sin soporte no recibe.
     *
     * @return Collection<int, Announcement>
     */
    public function visibleFor(User $user, Tenant $tenant): Collection
    {
        if ($tenant->is_platform) {
            return new Collection;
        }

        $isAdmin = $this->isAdminOf($user, $tenant);

        return Announcement::query()
            ->liveFor($tenant)
            ->when(! $isAdmin, fn ($q) => $q->where('admins_only', false))
            ->with(['reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();
    }

    public function markRead(Announcement $announcement, User $user, bool $dismiss = false): void
    {
        $read = AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        if ($dismiss && $read->dismissed_at === null) {
            $read->forceFill(['dismissed_at' => now()])->save();
        }
    }

    public function markAllRead(User $user, Tenant $tenant): void
    {
        $now = now();
        $rows = $this->visibleFor($user, $tenant)
            ->filter(fn (Announcement $a) => $a->reads->isEmpty())
            ->map(fn (Announcement $a) => ['announcement_id' => $a->id, 'user_id' => $user->id, 'read_at' => $now])
            ->values()
            ->all();

        if ($rows !== []) {
            AnnouncementRead::query()->insertOrIgnore($rows);
        }
    }

    /** @param  array<string, mixed>  $data */
    public function save(?Announcement $announcement, array $data, User $actor): Announcement
    {
        return DB::transaction(function () use ($announcement, $data, $actor) {
            $audience = AnnouncementAudience::from($data['audience']);
            $announcement ??= new Announcement(['created_by' => $actor->id]);

            $announcement->fill([
                ...collect($data)->only(['type', 'title', 'body', 'link_url', 'link_label', 'admins_only', 'published_at', 'expires_at'])->all(),
                'audience' => $audience,
                'plan_keys' => $audience === AnnouncementAudience::Plans ? array_values($data['plan_keys'] ?? []) : null,
                // El tipo manda: una promoción nunca va por correo.
                'send_email' => (bool) ($data['send_email'] ?? false) && AnnouncementType::from($data['type'])->canEmail(),
            ]);
            $isNew = ! $announcement->exists;
            $announcement->save();

            $announcement->tenants()->sync($audience === AnnouncementAudience::Tenants ? ($data['tenant_ids'] ?? []) : []);

            $this->audit->record($isNew ? 'announcement.created' : 'announcement.updated', null, $announcement, [
                'type' => $announcement->type->value,
                'status' => $announcement->status(),
            ]);

            return $announcement->load('tenants');
        });
    }

    public function resolve(Announcement $announcement): Announcement
    {
        $announcement->forceFill(['resolved_at' => now()])->save();
        $this->audit->record('announcement.resolved', null, $announcement);

        return $announcement;
    }

    /**
     * Usuarios activos a los que va dirigido (para estadísticas y correo).
     *
     * @return SupportCollection<int, User>
     */
    public function recipients(Announcement $announcement): SupportCollection
    {
        $tenants = Tenant::query()
            ->where('is_platform', false)
            ->when($announcement->audience === AnnouncementAudience::Plans, fn ($q) => $q->whereIn('plan', $announcement->plan_keys ?? []))
            ->when($announcement->audience === AnnouncementAudience::Tenants, fn ($q) => $q->whereIn('id', $announcement->tenants()->pluck('tenants.id')))
            ->get();

        return $tenants->flatMap(function (Tenant $tenant) use ($announcement) {
            $users = User::query()->forTenant($tenant->id)->whereNull('deactivated_at')->get();

            return $announcement->admins_only
                ? $users->filter(fn (User $user) => $this->isAdminOf($user, $tenant))
                : $users;
        })->values();
    }

    /**
     * Lecturas por cliente: [{tenant_id, tenant_name, recipients, reads}].
     *
     * @return list<array{tenant_id: int, tenant_name: string, recipients: int, reads: int}>
     */
    public function stats(Announcement $announcement): array
    {
        $readers = $announcement->reads()->pluck('user_id')->flip();

        return $this->recipients($announcement)
            ->groupBy('tenant_id')
            ->map(fn (SupportCollection $users, int $tenantId) => [
                'tenant_id' => $tenantId,
                'tenant_name' => (string) Tenant::query()->whereKey($tenantId)->value('name'),
                'recipients' => $users->count(),
                'reads' => $users->filter(fn (User $user) => $readers->has($user->id))->count(),
            ])
            ->sortBy('tenant_name')
            ->values()
            ->all();
    }

    /** Tarea programada: correos de los comunicados ya publicados que lo piden. */
    public function sendDueEmails(): int
    {
        $sent = 0;

        Announcement::query()
            ->where('send_email', true)
            ->whereNull('emailed_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->each(function (Announcement $announcement) use (&$sent) {
                // Primero se marca: si el envío se reintenta, no duplica correos.
                $announcement->forceFill(['emailed_at' => now()])->save();

                if (! $announcement->type->canEmail()) {
                    return;
                }

                $recipients = $this->recipients($announcement);
                Notification::send($recipients, new AnnouncementMail($announcement->id));
                $sent += $recipients->count();
            });

        return $sent;
    }

    /**
     * Administrador del cliente (users.manage en su tenant). El equipo de la
     * plataforma en modo soporte ve lo mismo que un administrador.
     */
    public function isAdminOf(User $user, Tenant $tenant): bool
    {
        if ($user->tenant_id !== $tenant->id) {
            return (bool) Tenant::query()->whereKey($user->tenant_id)->value('is_platform');
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId($tenant->id);

        try {
            return $user->unsetRelation('roles')->unsetRelation('permissions')->hasPermissionTo(self::ADMIN_PERMISSION);
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}
