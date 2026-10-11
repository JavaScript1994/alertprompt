<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SetPasswordLink;
use App\Services\AuditLogger;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use App\Services\Users\UserProfileService;
use App\Support\TenantContext;
use App\Support\TenantSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Alta y ciclo de vida de clientes (empresas y personas naturales) desde el
 * panel de la plataforma.
 */
class ClientManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantModules $modules,
    ) {}

    /**
     * Crea el tenant y su administrador inicial, y le envía la invitación para
     * definir su contraseña. Nadie (ni el dueño) conoce esa contraseña.
     *
     * Los datos personales del administrador llegan con prefijo `admin_`
     * (admin_first_name, admin_last_name, admin_job_title...); la foto, aparte.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $adminPhoto = null): Tenant
    {
        [$tenant, $admin] = DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => TenantSlug::unique((string) $data['name']),
                'type' => TenantType::from($data['type']),
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'plan' => $data['plan'] ?? Plan::defaultKey(),
                'status' => TenantStatus::Active,
                'settings' => ['timezone' => 'America/Lima'],
            ]);

            $person = [];
            foreach (UserProfileService::PERSONAL_FIELDS as $field) {
                $person[$field] = $data["admin_{$field}"] ?? null;
            }

            $admin = $this->createUser($tenant, $person, $data['admin_email'], 'client-admin');
            $this->modules->sync($tenant, $this->modules->defaultsFor((string) $tenant->plan));

            // Un cliente siempre tiene membresía: nace con la de su plan.
            app(MembershipManager::class)->startFromCatalog($tenant, (string) $tenant->plan);

            $this->audit->record('client.created', $tenant->id, $tenant, [
                'admin_email' => $admin->email,
            ]);

            return [$tenant, $admin];
        });

        // Fuera de la transacción: si el alta fallara, no queda un archivo huérfano.
        if ($adminPhoto !== null) {
            app(UserProfileService::class)->setPhoto($admin, $adminPhoto);
        }

        $this->invite($admin, $tenant);

        return $tenant;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->fill($data);
        $changes = array_keys($tenant->getDirty());
        $tenant->save();

        if ($changes !== []) {
            $this->audit->record('client.updated', $tenant->id, $tenant, ['fields' => $changes]);
        }

        return $tenant;
    }

    public function setStatus(Tenant $tenant, TenantStatus $status, ?string $reason = null): Tenant
    {
        $previous = $tenant->status;
        $tenant->update(['status' => $status]);

        $this->audit->record($status === TenantStatus::Suspended ? 'client.suspended' : 'client.reactivated', $tenant->id, $tenant, [
            'from' => $previous->value,
            'to' => $status->value,
            'reason' => $reason,
        ]);

        return $tenant;
    }

    /**
     * Usuario con contraseña aleatoria imposible de adivinar: el acceso real
     * llega por el enlace de invitación.
     */
    /**
     * @param  string|array<string, mixed>  $person  nombre visible (altas simples, seeders) o
     *                                               datos personales (first_name, last_name...)
     */
    public function createUser(Tenant $tenant, string|array $person, string $email, string $role): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            ...(is_string($person) ? ['name' => $person] : $person),
            'email' => $email,
            'password' => Str::random(64),
        ]);

        TenantContext::withPermissionsOf($tenant->id, fn () => $user->assignRole($role));

        return $user;
    }

    public function invite(User $user, Tenant $tenant): void
    {
        $token = Password::broker('invitations')->createToken($user);

        $user->notify(new SetPasswordLink($token, invitation: true, tenantName: $tenant->name));
    }
}
