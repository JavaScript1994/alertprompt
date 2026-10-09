<?php

declare(strict_types=1);

namespace App\Services\Memberships;

use App\Enums\AlertSeverity;
use App\Enums\BillingCycle;
use App\Enums\PlanChangeStatus;
use App\Models\Alert;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Alerts;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El cliente pide un cambio de plan; la plataforma lo aprueba (crea la nueva
 * membresía con las condiciones del catálogo) o lo rechaza. Un cliente tiene
 * a lo sumo una solicitud pendiente.
 */
class PlanChangeManager
{
    public function __construct(
        private readonly MembershipManager $memberships,
        private readonly AuditLogger $audit,
        private readonly Alerts $alerts,
    ) {}

    public function pendingFor(int $tenantId): ?PlanChangeRequest
    {
        return PlanChangeRequest::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('status', PlanChangeStatus::Pending)
            ->first();
    }

    public function request(Tenant $tenant, User $user, string $planKey, ?string $comment): PlanChangeRequest
    {
        $plan = Plan::query()->active()->where('is_public', true)->where('key', $planKey)->first();
        $current = $this->memberships->current($tenant->id)?->plan ?? $tenant->plan;

        if ($plan === null) {
            throw ValidationException::withMessages(['plan' => 'Ese plan no está disponible.']);
        }
        if ($plan->key === $current) {
            throw ValidationException::withMessages(['plan' => 'Ya tienes ese plan.']);
        }
        if ($this->pendingFor($tenant->id) !== null) {
            throw ValidationException::withMessages(['plan' => 'Ya tienes una solicitud pendiente. Cancélala para pedir otro plan.']);
        }

        $request = PlanChangeRequest::query()->create([
            'tenant_id' => $tenant->id,
            'current_plan' => $current,
            'requested_plan' => $plan->key,
            'status' => PlanChangeStatus::Pending,
            'comment' => $comment,
            'requested_by' => $user->id,
        ]);

        $this->audit->record('plan_change.requested', $tenant->id, $request, ['from' => $current, 'to' => $plan->key]);
        $this->alerts->raise(
            tenantId: $tenant->id,
            type: 'plan_change.requested',
            severity: AlertSeverity::Info,
            title: "{$tenant->name} pide cambiar al plan {$plan->name}",
            message: 'Revísalo en Facturación > Solicitudes de plan.'.($comment ? " Comentario: «{$comment}»" : ''),
            subjectKey: "plan-change-{$request->id}",
        );

        return $request;
    }

    public function cancel(PlanChangeRequest $request): PlanChangeRequest
    {
        $this->ensurePending($request);
        $request->update(['status' => PlanChangeStatus::Cancelled, 'decided_at' => now()]);
        $this->audit->record('plan_change.cancelled', $request->tenant_id, $request);
        $this->resolveAlert($request, null);

        return $request;
    }

    /**
     * $when: 'now' (hoy, reemplaza la vigente) o 'next_period' (al empezar el
     * siguiente ciclo de facturación; la vigente termina el día anterior).
     */
    public function approve(PlanChangeRequest $request, User $admin, string $when, ?string $note): PlanChangeRequest
    {
        $this->ensurePending($request);

        return DB::transaction(function () use ($request, $admin, $when, $note) {
            $tenant = Tenant::query()->findOrFail($request->tenant_id);
            $current = $this->memberships->current($tenant->id);
            $startsAt = $when === 'next_period' && $current !== null
                ? $this->nextPeriodStart($current)
                : CarbonImmutable::today();

            if ($current !== null && $startsAt->isAfter(CarbonImmutable::today())) {
                $current->update(['ends_at' => $startsAt->subDay()]);
            }

            $membership = $this->memberships->startFromCatalog(
                $tenant,
                $request->requested_plan,
                $startsAt,
                $current?->billing_cycle->value,
                "Cambio de plan aprobado (solicitud #{$request->id}).",
            );

            $request->update([
                'status' => PlanChangeStatus::Approved,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'effective_from' => $startsAt,
                'membership_id' => $membership->id,
            ]);

            $this->audit->record('plan_change.approved', $tenant->id, $request, [
                'to' => $request->requested_plan,
                'effective_from' => $startsAt->toDateString(),
            ]);
            $this->resolveAlert($request, $admin->id);

            return $request;
        });
    }

    public function reject(PlanChangeRequest $request, User $admin, string $note): PlanChangeRequest
    {
        $this->ensurePending($request);
        $request->update([
            'status' => PlanChangeStatus::Rejected,
            'decided_by' => $admin->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
        $this->audit->record('plan_change.rejected', $request->tenant_id, $request, ['note' => $note]);
        $this->resolveAlert($request, $admin->id);

        return $request;
    }

    private function nextPeriodStart(Membership $current): CarbonImmutable
    {
        $start = CarbonImmutable::parse($current->starts_at);
        $months = $current->billing_cycle === BillingCycle::Yearly ? 12 : 1;
        $next = $start;

        while ($next->lessThanOrEqualTo(CarbonImmutable::today())) {
            $next = $next->addMonthsNoOverflow($months);
        }

        return $next;
    }

    private function ensurePending(PlanChangeRequest $request): void
    {
        if ($request->status !== PlanChangeStatus::Pending) {
            throw ValidationException::withMessages(['request' => 'La solicitud ya fue atendida.']);
        }
    }

    private function resolveAlert(PlanChangeRequest $request, ?int $userId): void
    {
        Alert::query()->withoutGlobalScope(TenantScope::class)
            ->where('fingerprint', "{$request->tenant_id}:plan_change.requested:plan-change-{$request->id}")
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by' => $userId]);
    }
}
