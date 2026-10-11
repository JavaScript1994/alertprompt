<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Enums\MembershipStatus;
use App\Models\CreditApplication;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Scopes\TenantScope;
use App\Models\TenantAddOn;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Notas de crédito y saldo a favor (CLAUDE.md §2.7). Una nota primero salda
 * lo pendiente del comprobante que corrige, luego los otros comprobantes
 * pendientes del cliente (el más antiguo primero); lo que sobra queda a favor
 * y se aplica solo a sus comprobantes siguientes.
 */
class CreditNoteManager
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Servicio cancelado en una membresía ANUAL: devuelve los meses ya
     * cobrados que no se usarán (el mes en curso no se devuelve). Nada en
     * las mensuales: el período en curso se cobra y el siguiente ya no.
     */
    public function issueForCancelledAddOn(TenantAddOn $contract, ?CarbonImmutable $today = null): ?CreditNote
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

        $periodStart = BillingPeriod::currentStart($membership, $today);
        $alreadyIssued = CreditNote::query()->withoutGlobalScope(TenantScope::class)->where('tenant_add_on_id', $contract->id)->exists();
        if ($periodStart === null || $alreadyIssued) {
            return null;
        }

        [$invoice, $billed] = $this->billedLine($contract, $periodStart);
        $months = BillingPeriod::monthsLeftInYear($periodStart, $today);
        $amount = min(round((float) $contract->monthly_price * $months, 2), $billed);

        if ($invoice === null || $amount <= 0) {
            return null;
        }

        $from = $periodStart->addMonthsNoOverflow(12 - $months);
        $to = $periodStart->addYear()->subDay();
        $name = $contract->addOn->name;
        $description = "Devolución del servicio {$name}: {$months} ".($months === 1 ? 'mes no usado' : 'meses no usados')." del {$from->format('d/m/Y')} al {$to->format('d/m/Y')}";

        return $this->issue($invoice, $amount, "Cancelación del servicio {$name}", [['description' => $description, 'amount' => $this->money($amount)]], $contract);
    }

    /**
     * Emite la nota (monto SIN IGV) y la aplica al saldo del comprobante que corrige.
     *
     * @param  list<array{description: string, amount: string}>  $lines
     */
    public function issue(Invoice $invoice, float $subtotal, string $reason, array $lines, ?TenantAddOn $contract = null): CreditNote
    {
        $igv = round($subtotal * (float) config('billing.igv_rate'), 2);
        $series = config('billing.series.nota_credito_'.$invoice->document_type);

        return DB::transaction(function () use ($invoice, $subtotal, $igv, $series, $reason, $lines, $contract) {
            $note = CreditNote::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'tenant_add_on_id' => $contract?->id,
                'series' => $series,
                'number' => $this->numbers->next($series),
                'issue_date' => CarbonImmutable::today(),
                'currency' => $invoice->currency,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => round($subtotal + $igv, 2),
                'reason' => $reason,
                'lines' => $lines,
                'customer' => $invoice->customer,
                'created_by' => Auth::id(),
            ]);

            $this->audit->record('credit_note.issued', $invoice->tenant_id, $note, [
                'code' => $note->code(),
                'invoice' => $invoice->code(),
                'total' => (string) $note->total,
            ]);

            if ($invoice->status === InvoiceStatus::Issued) {
                $this->apply($note, $invoice, min($note->remaining(), $invoice->balance()));
            }
            $this->applyToPendingInvoices($note);

            return $note;
        });
    }

    /** Saldo a favor del cliente: lo que sus notas aún no saldaron. */
    public function availableFor(int $tenantId): float
    {
        return round(CreditNote::query()->forTenant($tenantId)->get()->sum(fn (CreditNote $note) => $note->remaining()), 2);
    }

    /** Descuenta el saldo a favor de un comprobante recién emitido (notas más antiguas primero). */
    public function applyAvailableCredit(Invoice $invoice): void
    {
        $notes = CreditNote::query()->forTenant($invoice->tenant_id)->orderBy('id')->lockForUpdate()->get();

        foreach ($notes as $note) {
            $due = $invoice->balance();
            if ($due <= 0) {
                return;
            }
            $this->apply($note, $invoice, min($note->remaining(), $due));
        }
    }

    /** Lo que queda de la nota salda los demás comprobantes pendientes, del más antiguo al más nuevo. */
    private function applyToPendingInvoices(CreditNote $note): void
    {
        $pending = Invoice::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $note->tenant_id)
            ->where('status', InvoiceStatus::Issued)
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get();

        foreach ($pending as $invoice) {
            $left = $note->remaining();
            if ($left <= 0) {
                return;
            }
            $this->apply($note, $invoice, min($left, $invoice->balance()));
        }
    }

    /** Al anular un comprobante, lo que se le aplicó vuelve a ser saldo a favor. */
    public function release(Invoice $invoice): void
    {
        $invoice->creditApplications()->delete();
    }

    private function apply(CreditNote $note, Invoice $invoice, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        CreditApplication::query()->create(['credit_note_id' => $note->id, 'invoice_id' => $invoice->id, 'amount' => $amount]);
        $this->audit->record('credit_note.applied', $invoice->tenant_id, $note, ['invoice' => $invoice->code(), 'amount' => $this->money($amount)]);

        if ($invoice->balance() <= 0) {
            $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        }
    }

    /**
     * Comprobante (no anulado) de este período que cobró el servicio, y cuánto
     * se cobró: el prorrateo al contratar o la línea de la factura anual.
     *
     * @return array{0: ?Invoice, 1: float}
     */
    private function billedLine(TenantAddOn $contract, CarbonImmutable $periodStart): array
    {
        $invoices = Invoice::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $contract->tenant_id)
            ->where('status', '!=', InvoiceStatus::Void)
            ->where('issue_date', '>=', $periodStart->toDateString())
            ->latest('id')
            ->get();

        foreach ($invoices as $invoice) {
            foreach ($invoice->lines ?? [] as $line) {
                if (($line['tenant_add_on_id'] ?? null) === $contract->id) {
                    return [$invoice, (float) $line['amount']];
                }
            }
        }

        return [null, 0.0];
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
