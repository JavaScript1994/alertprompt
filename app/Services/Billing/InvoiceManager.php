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
use App\Services\Alerts;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /** Emite un comprobante por un monto SIN IGV. */
    public function issue(Tenant $tenant, string $subtotal, string $description, ?Membership $membership = null, ?CarbonImmutable $periodStart = null): Invoice
    {
        $documentType = $tenant->document_type === DocumentType::Ruc ? 'factura' : 'boleta';
        $series = config("billing.series.{$documentType}");
        $subtotal = round((float) $subtotal, 2);
        $igv = round($subtotal * (float) config('billing.igv_rate'), 2);
        $today = CarbonImmutable::today();

        $invoice = DB::transaction(function () use ($tenant, $documentType, $series, $subtotal, $igv, $description, $membership, $periodStart, $today) {
            DB::table('invoice_sequences')->insertOrIgnore(['series' => $series, 'last_number' => 0]);
            $number = (int) DB::table('invoice_sequences')->where('series', $series)->lockForUpdate()->value('last_number') + 1;
            DB::table('invoice_sequences')->where('series', $series)->update(['last_number' => $number]);

            $invoice = Invoice::query()->create([
                'tenant_id' => $tenant->id,
                'membership_id' => $membership?->id,
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
                'description' => $description,
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

    /** Comprobante del período vigente de una membresía, si aún no se emitió. */
    public function issueForMembershipPeriod(Membership $membership, CarbonImmutable $today): ?Invoice
    {
        $periodStart = $this->currentPeriodStart($membership, $today);

        if ($periodStart === null || $membership->price <= 0) {
            return null;
        }

        $exists = Invoice::query()->withoutGlobalScope(TenantScope::class)
            ->where('membership_id', $membership->id)
            ->where('period_start', $periodStart)
            ->exists();

        if ($exists) {
            return null;
        }

        $cycle = $membership->billing_cycle === BillingCycle::Monthly ? 'mensual' : 'anual';
        $periodEnd = $membership->billing_cycle === BillingCycle::Monthly ? $periodStart->addMonth()->subDay() : $periodStart->addYear()->subDay();

        return $this->issue(
            Tenant::query()->findOrFail($membership->tenant_id),
            (string) $membership->price,
            "Plan {$this->planName($membership->plan)} ({$cycle}) del {$periodStart->format('d/m/Y')} al {$periodEnd->format('d/m/Y')}",
            $membership,
            $periodStart,
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
