<?php

declare(strict_types=1);

namespace App\Services\AddOns;

use App\Enums\TenantAddOnStatus;
use App\Models\AddOn;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TenantAddOn;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\InvoiceManager;
use App\Services\Modules\TenantModules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contratos de servicios adicionales. Un contrato activo prende el módulo del
 * servicio; cancelarlo lo apaga. Al cambiar de plan, el cliente queda con los
 * módulos del plan nuevo más los que tiene contratados, y se cancela lo que
 * el plan nuevo ya incluye (no se cobra dos veces).
 *
 * Cobro: entra en la factura del plan desde el período siguiente a la
 * contratación (InvoiceManager); en membresías anuales, el resto del año se
 * prorratea en un comprobante aparte al contratar.
 */
class AddOnManager
{
    public function __construct(
        private readonly TenantModules $modules,
        private readonly AuditLogger $audit,
        private readonly InvoiceManager $invoices,
    ) {}

    /** @return Collection<int, TenantAddOn> */
    public function activeFor(int $tenantId)
    {
        return TenantAddOn::query()->forTenant($tenantId)
            ->where('status', TenantAddOnStatus::Active)
            ->with('addOn')
            ->get();
    }

    /** Módulos que el cliente tiene por servicios contratados. @return list<string> */
    public function contractedModules(int $tenantId): array
    {
        return $this->activeFor($tenantId)
            ->map(fn (TenantAddOn $contract) => $contract->addOn->module)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function contract(Tenant $tenant, AddOn $addOn, ?User $by = null, ?CarbonImmutable $startsAt = null): TenantAddOn
    {
        if ($tenant->is_platform || ! $addOn->isAvailable()) {
            throw ValidationException::withMessages(['add_on' => 'Ese servicio no está disponible.']);
        }
        if ($this->modules->isEnabled($addOn->module, $tenant)) {
            throw ValidationException::withMessages(['add_on' => "El cliente ya tiene «{$addOn->name}» activo."]);
        }

        $contract = DB::transaction(function () use ($tenant, $addOn, $by, $startsAt) {
            $contract = TenantAddOn::query()->create([
                'tenant_id' => $tenant->id,
                'add_on_id' => $addOn->id,
                'status' => TenantAddOnStatus::Active,
                'monthly_price' => (string) ($addOn->monthly_price ?? 0),
                'starts_at' => ($startsAt ?? CarbonImmutable::today())->toDateString(),
                'activated_by' => $by?->id,
            ]);

            $this->modules->sync($tenant, [...$this->modules->enabledFor($tenant), $addOn->module]);
            $this->audit->record('add_on.contracted', $tenant->id, $contract, [
                'add_on' => $addOn->key,
                'monthly_price' => (string) $contract->monthly_price,
            ]);

            return $contract;
        });

        // Membresía anual: los meses que faltan del año se cobran aparte (en
        // las mensuales entra en la factura del período siguiente). Tras el
        // commit: si se contrata dentro de otra transacción (aprobar una
        // solicitud), el comprobante no se envía a SUNAT antes de confirmarla.
        DB::afterCommit(fn () => $this->invoices->issueAddOnProration($contract));

        return $contract;
    }

    public function cancel(TenantAddOn $contract, ?User $by = null, ?string $reason = null): TenantAddOn
    {
        if ($contract->status !== TenantAddOnStatus::Active) {
            throw ValidationException::withMessages(['add_on' => 'Ese servicio ya no está activo.']);
        }

        return DB::transaction(function () use ($contract, $by, $reason) {
            $this->markCancelled($contract, $by, $reason);

            $tenant = Tenant::query()->findOrFail($contract->tenant_id);
            $module = $contract->addOn->module;
            // Si el plan lo incluye, el módulo se queda.
            if ($module !== null && ! in_array($module, $this->modules->defaultsFor((string) $tenant->plan), true)) {
                $this->modules->sync($tenant, array_values(array_diff($this->modules->enabledFor($tenant), [$module])));
            }

            $this->audit->record('add_on.cancelled', $tenant->id, $contract, ['add_on' => $contract->addOn->key, 'reason' => $reason]);

            return $contract;
        });
    }

    /**
     * Tras un cambio de plan: cancela los servicios que el plan nuevo ya
     * incluye y deja los módulos del plan más los contratados.
     */
    public function reconcileWithPlan(Tenant $tenant, string $planKey): void
    {
        DB::transaction(function () use ($tenant, $planKey) {
            $planModules = $this->modules->defaultsFor($planKey);

            foreach ($this->activeFor($tenant->id) as $contract) {
                if (in_array($contract->addOn->module, $planModules, true)) {
                    $this->markCancelled($contract, null, 'Incluido en el plan nuevo.');
                    $this->audit->record('add_on.included_in_plan', $tenant->id, $contract, ['add_on' => $contract->addOn->key, 'plan' => $planKey]);
                }
            }

            $this->modules->sync($tenant, array_values(array_unique([...$planModules, ...$this->contractedModules($tenant->id)])));
        });
    }

    private function markCancelled(TenantAddOn $contract, ?User $by, ?string $reason): void
    {
        TenantAddOn::query()->withoutGlobalScope(TenantScope::class)->whereKey($contract->id)->update([
            'status' => TenantAddOnStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $by?->id,
            'cancel_reason' => $reason,
        ]);
        $contract->refresh();
    }
}
