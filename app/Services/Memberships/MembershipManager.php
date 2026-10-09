<?php

declare(strict_types=1);

namespace App\Services\Memberships;

use App\Enums\AlertSeverity;
use App\Enums\Channel;
use App\Enums\MembershipStatus;
use App\Enums\TenantPlan;
use App\Models\Membership;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Alerts;
use App\Services\AuditLogger;
use App\Services\Billing\InvoiceManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida de las membresías. Un cliente tiene a lo sumo una activa;
 * al activarse una nueva, la anterior vence. El plan del tenant sigue al de
 * su membresía activa. Todo explícito por tenant: corre también en el
 * scheduler, sin tenant activo.
 */
class MembershipManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Alerts $alerts,
    ) {}

    /** @return Builder<Membership> */
    public function queryFor(int $tenantId): Builder
    {
        return Membership::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId);
    }

    public function current(int $tenantId): ?Membership
    {
        return $this->queryFor($tenantId)->where('status', MembershipStatus::Active)->latest('starts_at')->first();
    }

    /**
     * @param  array{plan: string, billing_cycle: string, price: float|string, starts_at: string, ends_at: string, quotas?: array<string, int|null>, contract_reference?: ?string, notes?: ?string}  $data
     */
    public function create(Tenant $tenant, array $data): Membership
    {
        $startsAt = CarbonImmutable::parse($data['starts_at'])->startOfDay();
        $endsAt = CarbonImmutable::parse($data['ends_at'])->startOfDay();

        $overlap = $this->queryFor($tenant->id)
            ->where('status', MembershipStatus::Scheduled)
            ->where('starts_at', '<=', $endsAt)
            ->where('ends_at', '>=', $startsAt)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'Ya hay una membresía programada que se cruza con estas fechas. Cancélala primero.',
            ]);
        }

        return DB::transaction(function () use ($tenant, $data, $startsAt, $endsAt) {
            $membership = Membership::query()->create([
                'tenant_id' => $tenant->id,
                'plan' => TenantPlan::from($data['plan']),
                'status' => MembershipStatus::Scheduled,
                'billing_cycle' => $data['billing_cycle'],
                'price' => $data['price'],
                'currency' => 'PEN',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'quotas' => $this->normalizeQuotas($data['quotas'] ?? []),
                'contract_reference' => $data['contract_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->audit->record('membership.created', $tenant->id, $membership, [
                'plan' => $membership->plan->value,
                'price' => (string) $membership->price,
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
            ]);

            if ($startsAt->lessThanOrEqualTo(CarbonImmutable::today())) {
                $this->activate($membership);
            }

            return $membership->fresh();
        });
    }

    public function cancel(Membership $membership, ?string $reason): Membership
    {
        if (in_array($membership->status, [MembershipStatus::Expired, MembershipStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['membership' => 'La membresía ya no está vigente.']);
        }

        $membership->update(['status' => MembershipStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        $this->audit->record('membership.cancelled', $membership->tenant_id, $membership, ['reason' => $reason]);

        return $membership;
    }

    /**
     * Tarea diaria: activa las que empiezan, vence las que terminaron y avisa
     * lo que está por vencer o quedó sin membresía.
     *
     * @return array{activated: int, expired: int}
     */
    public function refresh(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $activated = 0;
        $expired = 0;

        Membership::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', MembershipStatus::Scheduled)
            ->where('starts_at', '<=', $today)
            ->orderBy('starts_at')
            ->each(function (Membership $membership) use (&$activated) {
                $this->activate($membership);
                $activated++;
            });

        Membership::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', MembershipStatus::Active)
            ->where('ends_at', '<', $today)
            ->each(function (Membership $membership) use (&$expired) {
                $membership->update(['status' => MembershipStatus::Expired]);
                $expired++;

                if ($this->current($membership->tenant_id) === null) {
                    $this->alerts->raise(
                        tenantId: $membership->tenant_id,
                        type: 'membership.expired',
                        severity: AlertSeverity::Warning,
                        title: 'Membresía vencida sin renovación',
                        message: "La membresía venció el {$membership->ends_at->toDateString()} y no hay otra vigente. Renueva o decide si suspender al cliente.",
                        subjectKey: "membership-{$membership->id}",
                    );
                }
            });

        $warnUntil = $today->addDays((int) config('memberships.expiry_warning_days', 7));
        Membership::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', MembershipStatus::Active)
            ->whereBetween('ends_at', [$today, $warnUntil])
            ->each(function (Membership $membership) {
                $renewed = $this->queryFor($membership->tenant_id)->where('status', MembershipStatus::Scheduled)->exists();
                if (! $renewed) {
                    $this->alerts->raise(
                        tenantId: $membership->tenant_id,
                        type: 'membership.expiring',
                        severity: AlertSeverity::Info,
                        title: 'Membresía por vencer',
                        message: "Vence el {$membership->ends_at->toDateString()} y no tiene renovación programada.",
                        subjectKey: "membership-{$membership->id}",
                    );
                }
            });

        return ['activated' => $activated, 'expired' => $expired];
    }

    /**
     * Consumo del mes en curso por canal (mensajes enviados) frente a la cuota.
     *
     * @return array<string, array{used: int, quota: ?int}>
     */
    public function usage(int $tenantId, ?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now();
        $quotas = $this->current($tenantId)?->quotas ?? [];

        $used = DB::table('campaign_recipients as r')
            ->join('campaigns as c', 'c.id', '=', 'r.campaign_id')
            ->where('c.tenant_id', $tenantId)
            ->whereNotNull('r.sent_at')
            ->whereBetween('r.sent_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->groupBy('c.channel')
            ->selectRaw('c.channel, count(*) as total')
            ->pluck('total', 'channel');

        $result = [];
        foreach (Channel::cases() as $channel) {
            $result[$channel->value] = [
                'used' => (int) ($used[$channel->value] ?? 0),
                'quota' => isset($quotas[$channel->value]) ? (int) $quotas[$channel->value] : null,
            ];
        }

        return $result;
    }

    /** Lanza si $count mensajes más superan la cuota del canal (solo con enforce_quotas). */
    public function ensureWithinQuota(int $tenantId, Channel $channel, int $count): void
    {
        if (! config('memberships.enforce_quotas')) {
            return;
        }

        $usage = $this->usage($tenantId)[$channel->value];

        if ($usage['quota'] !== null && $usage['quota'] < $usage['used'] + $count) {
            abort(422, "Superarías la cuota mensual de {$channel->label()} de tu membresía ({$usage['used']} de {$usage['quota']} usados).");
        }
    }

    private function activate(Membership $membership): void
    {
        DB::transaction(function () use ($membership) {
            $this->queryFor($membership->tenant_id)
                ->where('status', MembershipStatus::Active)
                ->whereKeyNot($membership->id)
                ->update(['status' => MembershipStatus::Expired]);

            $membership->update(['status' => MembershipStatus::Active]);
            Tenant::query()->whereKey($membership->tenant_id)->update(['plan' => $membership->plan->value]);

            $this->audit->record('membership.activated', $membership->tenant_id, $membership, ['plan' => $membership->plan->value]);
        });

        // Primer comprobante del período, sin esperar a la tarea diaria.
        app(InvoiceManager::class)->issueForMembershipPeriod($membership->fresh(), CarbonImmutable::today());
    }

    /** @param  array<string, mixed>  $quotas @return array<string, int|null> */
    private function normalizeQuotas(array $quotas): array
    {
        $result = [];
        foreach (Channel::cases() as $channel) {
            $value = $quotas[$channel->value] ?? null;
            $result[$channel->value] = $value === null || $value === '' ? null : max(0, (int) $value);
        }

        return $result;
    }
}
