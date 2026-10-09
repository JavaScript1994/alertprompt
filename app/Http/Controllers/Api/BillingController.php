<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Billing\AddPaymentMethodRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\Billing\BillingProviders;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Facturación vista por el cliente: comprobantes, pagos y métodos de pago. */
class BillingController extends Controller
{
    public function summary(BillingProviders $providers): JsonResponse
    {
        $open = Invoice::query()->where('status', 'issued')->get();

        return response()->json(['data' => [
            'balance' => number_format($open->sum(fn (Invoice $i) => (float) $i->total - (float) $i->paidAmount()), 2, '.', ''),
            'overdue_count' => $open->filter->isOverdue()->count(),
            'transfer_instructions' => config('billing.transfer_instructions'),
            'cards_enabled' => $providers->gateway()->supportsCards(),
        ]]);
    }

    public function invoices(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            Invoice::query()->with('payments')->latest('issue_date')->latest('id')->paginate(min((int) $request->integer('per_page', 10), 50)),
        );
    }

    public function invoice(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('payments'));
    }

    public function payments(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection(
            Payment::query()->with('invoice')->latest('paid_at')->paginate(min((int) $request->integer('per_page', 10), 50)),
        );
    }

    public function paymentMethods(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(PaymentMethod::query()->latest('id')->get());
    }

    public function addPaymentMethod(AddPaymentMethodRequest $request, BillingProviders $providers, AuditLogger $audit): PaymentMethodResource
    {
        $method = $providers->gateway()->attachCard(Tenant::query()->findOrFail(TenantContext::id()), $request->validated());
        $audit->record('payment_method.added', $method->tenant_id, $method, ['brand' => $method->brand, 'last4' => $method->last4]);

        return new PaymentMethodResource($method);
    }

    public function removePaymentMethod(PaymentMethod $paymentMethod, AuditLogger $audit): JsonResponse
    {
        $audit->record('payment_method.removed', $paymentMethod->tenant_id, $paymentMethod, ['last4' => $paymentMethod->last4]);
        $paymentMethod->delete();

        return response()->json(status: 204);
    }
}
