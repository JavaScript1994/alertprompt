<?php

declare(strict_types=1);

namespace App\Services\Memberships;

use App\Enums\Channel;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\Billing\InvoiceManager;
use App\Services\Modules\TenantModules;
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
    public function __construct(private readonly AuditLogger $audit) {}

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
                'plan' => $data['plan'],
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
                'plan' => $membership->plan,
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

    /**
     * Solo se cancela una membresía PROGRAMADA. La vigente no: un cliente
     * siempre tiene membresía; para cambiarla se crea otra (cambio de plan).
     */
    /**
     * Membresía con las condiciones del catálogo para el plan $planKey, por
     * 12 meses desde $startsAt. Se usa al dar de alta un cliente y al aprobar
     * un cambio de plan.
     */
    public function startFromCatalog(Tenant $tenant, string $planKey, ?CarbonImmutable $startsAt = null, ?string $billingCycle = null, ?string $notes = null): Membership
    {
        $plan = Plan::query()->where('key', $planKey)->firstOrFail();
        $startsAt ??= CarbonImmutable::today();

        return $this->create($tenant, [
            'plan' => $plan->key,
            'billing_cycle' => $billingCycle ?? 'monthly',
            'price' => (string) (($billingCycle === 'yearly' ? 12 : 1) * (float) ($plan->monthly_price ?? 0)),
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $startsAt->addYear()->subDay()->toDateString(),
            'quotas' => $plan->quotas ?? [],
            'notes' => $notes,
        ]);
    }

    /** Renovación automática: mismas condiciones, mismo plazo, sin cortes. */
    public function renew(Membership $previous): Membership
    {
        $start = CarbonImmutable::parse($previous->ends_at)->addDay();
        $months = max(1, (int) round(CarbonImmutable::parse($previous->starts_at)->diffInMonths($start)));

        $renewal = $this->create(Tenant::query()->findOrFail($previous->tenant_id), [
            'plan' => $previous->plan,
            'billing_cycle' => $previous->billing_cycle->value,
            'price' => (string) $previous->price,
            'starts_at' => $start->toDateString(),
            'ends_at' => $start->addMonthsNoOverflow($months)->subDay()->toDateString(),
            'quotas' => $previous->quotas ?? [],
            'contract_reference' => $previous->contract_reference,
            'notes' => "Renovación automática de la membresía #{$previous->id}.",
        ]);

        $this->audit->record('membership.renewed', $previous->tenant_id, $renewal, ['from' => $previous->id]);

        return $renewal;
    }

    public function cancel(Membership $membership, ?string $reason): Membership
    {
        if ($membership->status !== MembershipStatus::Scheduled) {
            throw ValidationException::withMessages([
                'membership' => 'Solo se cancela una membresía programada. La vigente se reemplaza con un cambio de plan.',
            ]);
        }

        $membership->update(['status' => MembershipStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        $this->audit->record('membership.cancelled', $membership->tenant_id, $membership, ['reason' => $reason]);

        return $membership;
    }

    /**
     * Tarea diaria: activa las que empiezan y renueva las que terminaron sin
     * sucesora (un cliente nunca queda sin membresía).
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

        // Un cliente siempre tiene membresía: la que vence sin sucesora se
        // renueva sola con las mismas condiciones y el mismo plazo.
        Membership::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', MembershipStatus::Active)
            ->where('ends_at', '<', $today)
            ->each(function (Membership $membership) use (&$expired) {
                $membership->update(['status' => MembershipStatus::Expired]);
                $expired++;

                $hasSuccessor = $this->queryFor($membership->tenant_id)
                    ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Scheduled])
                    ->exists();

                if (! $hasSuccessor) {
                    $this->renew($membership);
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
            $previousPlan = $this->current($membership->tenant_id)?->plan;

            $this->queryFor($membership->tenant_id)
                ->where('status', MembershipStatus::Active)
                ->whereKeyNot($membership->id)
                ->update(['status' => MembershipStatus::Expired]);

            $membership->update(['status' => MembershipStatus::Active]);
            Tenant::query()->whereKey($membership->tenant_id)->update(['plan' => $membership->plan]);

            $this->audit->record('membership.activated', $membership->tenant_id, $membership, ['plan' => $membership->plan]);

            // Cambio de plan: el cliente pasa a los módulos de su nuevo plan.
            if ($previousPlan !== null && $previousPlan !== $membership->plan) {
                $tenant = Tenant::query()->findOrFail($membership->tenant_id);
                app(TenantModules::class)->sync($tenant, app(TenantModules::class)->defaultsFor($membership->plan));
            }
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
