<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AlertSeverity;
use App\Enums\BillingCycle;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethodType;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TenantAddOn;
use App\Services\Alerts;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Emisión de comprobantes, pagos y anulaciones. Todo explícito por tenant:
 * se usa desde el panel de la plataforma y desde el scheduler.
 */
class InvoiceManager
{
    public function __construct(
        private readonly BillingProviders $providers,
        private readonly AuditLogger $audit,
        private readonly Alerts $alerts,
    ) {}

    /**
     * Emite un comprobante por un monto SIN IGV.
     *
     * @param  list<array{description: string, amount: string}>  $lines  detalle; vacío = una línea con $description
     */
    public function issue(
        Tenant $tenant,
        string $subtotal,
        string $description,
        ?Membership $membership = null,
        ?CarbonImmutable $periodStart = null,
        array $lines = [],
        ?TenantAddOn $addOnContract = null,
    ): Invoice {
        $documentType = $tenant->document_type === DocumentType::Ruc ? 'factura' : 'boleta';
        $series = config("billing.series.{$documentType}");
        $subtotal = round((float) $subtotal, 2);
        $igv = round($subtotal * (float) config('billing.igv_rate'), 2);
        $today = CarbonImmutable::today();

        $lines = $lines !== [] ? $lines : [['description' => $description, 'amount' => number_format($subtotal, 2, '.', '')]];

        $invoice = DB::transaction(function () use ($tenant, $documentType, $series, $subtotal, $igv, $description, $membership, $periodStart, $today, $lines, $addOnContract) {
            DB::table('invoice_sequences')->insertOrIgnore(['series' => $series, 'last_number' => 0]);
            $number = (int) DB::table('invoice_sequences')->where('series', $series)->lockForUpdate()->value('last_number') + 1;
            DB::table('invoice_sequences')->where('series', $series)->update(['last_number' => $number]);

            $invoice = Invoice::query()->create([
                'tenant_id' => $tenant->id,
                'membership_id' => $membership?->id,
                'tenant_add_on_id' => $addOnContract?->id,
                'period_start' => $periodStart,
                'document_type' => $documentType,
                'series' => $series,
                'number' => $number,
                'issue_date' => $today,
                'due_date' => $today->addDays((int) config('billing.due_days')),
                'currency' => config('billing.currency'),
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => round($subtotal + $igv, 2),
                'description' => Str::limit($description, 250),
                'lines' => $lines,
                'customer' => [
                    'name' => $tenant->name,
                    'document_type' => $tenant->document_type?->value,
                    'document_number' => $tenant->document_number,
                    'address' => $tenant->address,
                ],
                'status' => InvoiceStatus::Issued,
                'created_by' => Auth::id(),
            ]);

            $this->audit->record('invoice.issued', $tenant->id, $invoice, ['code' => $invoice->code(), 'total' => (string) $invoice->total]);

            return $invoice;
        });

        // El envío a SUNAT va fuera de la transacción: si el proveedor falla,
        // el comprobante existe y queda pendiente de reenvío.
        try {
            $result = $this->providers->einvoicing()->send($invoice);
            $invoice->update([
                'sunat_status' => $result->status,
                'provider_reference' => $result->providerReference,
                'pdf_url' => $result->pdfUrl,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $invoice;
    }

    /**
     * Comprobante del período vigente de una membresía, si aún no se emitió:
     * el plan más los servicios adicionales contratados ANTES de que empiece
     * el período (uno contratado a mitad de período entra en el siguiente; en
     * las anuales, ese tramo se cobra aparte con issueAddOnProration).
     */
    public function issueForMembershipPeriod(Membership $membership, CarbonImmutable $today): ?Invoice
    {
        $periodStart = $this->currentPeriodStart($membership, $today);

        if ($periodStart === null) {
            return null;
        }

        $exists = Invoice::query()->withoutGlobalScope(TenantScope::class)
            ->where('membership_id', $membership->id)
            ->where('period_start', $periodStart)
            ->exists();

        if ($exists) {
            return null;
        }

        $yearly = $membership->billing_cycle === BillingCycle::Yearly;
        $cycle = $yearly ? 'anual' : 'mensual';
        $periodEnd = $yearly ? $periodStart->addYear()->subDay() : $periodStart->addMonth()->subDay();
        $planLine = "Plan {$this->planName($membership->plan)} ({$cycle}) del {$periodStart->format('d/m/Y')} al {$periodEnd->format('d/m/Y')}";

        $lines = (float) $membership->price > 0 ? [['description' => $planLine, 'amount' => $this->money((float) $membership->price)]] : [];
        $addOns = $this->billableAddOns($membership->tenant_id, $periodStart);
        foreach ($addOns as $contract) {
            $lines[] = [
                'description' => "Servicio {$contract->addOn->name} ({$cycle})",
                'amount' => $this->money((float) $contract->monthly_price * ($yearly ? 12 : 1)),
            ];
        }

        $subtotal = array_sum(array_map(fn (array $line) => (float) $line['amount'], $lines));
        if ($subtotal <= 0) {
            return null;
        }

        $description = $addOns->isEmpty() ? $planLine : $planLine.' + '.$addOns->map(fn (TenantAddOn $contract) => $contract->addOn->name)->implode(' + ');

        return $this->issue(
            Tenant::query()->findOrFail($membership->tenant_id),
            $this->money($subtotal),
            $description,
            $membership,
            $periodStart,
            $lines,
        );
    }

    /**
     * Servicio contratado a mitad de una membresía ANUAL: un comprobante aparte
     * por los meses que faltan para la renovación (el mes en curso no se cobra,
     * igual que en las mensuales). Desde la renovación va en la factura anual.
     */
    public function issueAddOnProration(TenantAddOn $contract, ?CarbonImmutable $today = null): ?Invoice
    {
        $today ??= CarbonImmutable::today();
        $membership = Membership::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $contract->tenant_id)
            ->where('status', MembershipStatus::Active)
            ->latest('starts_at')
            ->first();

        if ($membership === null || $membership->billing_cycle !== BillingCycle::Yearly) {
            return null;
        }

        $periodStart = $this->currentPeriodStart($membership, $today);
        $alreadyIssued = Invoice::query()->withoutGlobalScope(TenantScope::class)->where('tenant_add_on_id', $contract->id)->exists();
        if ($periodStart === null || $alreadyIssued) {
            return null;
        }

        $startsAt = CarbonImmutable::parse($contract->starts_at)->startOfDay();
        $months = collect(range(1, 11))->filter(fn (int $k) => $periodStart->addMonthsNoOverflow($k)->greaterThan($startsAt))->count();
        $amount = (float) $contract->monthly_price * $months;

        if ($months === 0 || $amount <= 0) {
            return null;
        }

        $from = $periodStart->addMonthsNoOverflow(12 - $months);
        $to = $periodStart->addYear()->subDay();
        $name = $contract->addOn->name;
        $description = "Servicio {$name}: {$months} ".($months === 1 ? 'mes' : 'meses')." del {$from->format('d/m/Y')} al {$to->format('d/m/Y')} (plan anual)";

        return $this->issue(
            Tenant::query()->findOrFail($contract->tenant_id),
            $this->money($amount),
            $description,
            $membership,
            null,
            [['description' => $description, 'amount' => $this->money($amount)]],
            $contract,
        );
    }

    /**
     * @param  array{amount: string|float, method: string, paid_at?: ?string, reference?: ?string, notes?: ?string}  $data
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw ValidationException::withMessages(['invoice' => 'El comprobante está anulado.']);
        }

        $pending = round((float) $invoice->total - (float) $invoice->paidAmount(), 2);
        $amount = round((float) $data['amount'], 2);

        if ($amount > $pending + 0.001) {
            throw ValidationException::withMessages(['amount' => "El monto supera el saldo pendiente (S/ {$pending})."]);
        }

        return DB::transaction(function () use ($invoice, $data, $amount) {
            $payment = Payment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => PaymentMethodType::from($data['method']),
                'provider' => 'manual',
                'provider_reference' => $data['reference'] ?? null,
                'status' => 'confirmed',
                'paid_at' => isset($data['paid_at']) ? CarbonImmutable::parse($data['paid_at']) : now(),
                'recorded_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            if ((float) $invoice->paidAmount() + 0.001 >= (float) $invoice->total) {
                $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => $payment->paid_at]);
            }

            $this->audit->record('payment.recorded', $invoice->tenant_id, $payment, [
                'invoice' => $invoice->code(),
                'amount' => (string) $payment->amount,
                'method' => $payment->method->value,
            ]);

            return $payment;
        });
    }

    public function void(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Issued || $invoice->payments()->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Solo se anula un comprobante emitido y sin pagos.']);
        }

        $invoice->update(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'void_reason' => $reason]);
        $this->audit->record('invoice.voided', $invoice->tenant_id, $invoice, ['code' => $invoice->code(), 'reason' => $reason]);

        return $invoice;
    }

    /**
     * Tarea diaria: emite los comprobantes de período pendientes y alerta
     * los vencidos.
     *
     * @return array{issued: int, overdue: int}
     */
    public function runDaily(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $issued = 0;

        Membership::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', MembershipStatus::Active)
            ->each(function (Membership $membership) use ($today, &$issued) {
                if ($this->issueForMembershipPeriod($membership, $today) !== null) {
                    $issued++;
                }
            });

        $overdue = Invoice::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', InvoiceStatus::Issued)
            ->where('due_date', '<', $today)
            ->get();

        foreach ($overdue as $invoice) {
            $this->alerts->raise(
                tenantId: $invoice->tenant_id,
                type: 'invoice.overdue',
                severity: AlertSeverity::Warning,
                title: "Comprobante {$invoice->code()} vencido",
                message: "Venció el {$invoice->due_date->format('d/m/Y')} por S/ {$invoice->total}. Coordina el pago o decide si suspender al cliente.",
                subjectKey: "invoice-{$invoice->id}",
            );
        }

        return ['issued' => $issued, 'overdue' => $overdue->count()];
    }

    /**
     * Servicios que entran en el comprobante del período que empieza en
     * $periodStart: contratados antes de ese día y vigentes al cerrar su
     * primer día (así un cambio de plan que cancela lo que el plan nuevo
     * incluye no lo cobra en la factura que se emite en ese momento).
     *
     * @return Collection<int, TenantAddOn>
     */
    private function billableAddOns(int $tenantId, CarbonImmutable $periodStart): Collection
    {
        return TenantAddOn::query()->forTenant($tenantId)
            ->with('addOn')
            ->where('starts_at', '<', $periodStart->toDateString())
            ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $periodStart->addDay()))
            ->orderBy('id')
            ->get();
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    private function planName(string $key): string
    {
        return Plan::query()->where('key', $key)->value('name') ?? $key;
    }

    /** Inicio del ciclo de facturación que contiene $today (o null si fuera de vigencia). */
    private function currentPeriodStart(Membership $membership, CarbonImmutable $today): ?CarbonImmutable
    {
        $start = CarbonImmutable::parse($membership->starts_at)->startOfDay();
        $end = CarbonImmutable::parse($membership->ends_at)->startOfDay();

        if ($today->lessThan($start) || $today->greaterThan($end)) {
            return null;
        }

        $months = $membership->billing_cycle === BillingCycle::Monthly ? 1 : 12;
        $elapsed = (int) floor($start->diffInMonths($today) / $months) * $months;

        return $start->addMonthsNoOverflow($elapsed);
    }
}
