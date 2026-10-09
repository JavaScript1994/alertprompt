<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Billing\IssueInvoiceRequest;
use App\Http\Requests\Api\Admin\Billing\RecordPaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Billing\InvoiceManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Comprobantes de todos los clientes. Sin TenantScope a propósito: solo la
 * plataforma alcanza estas rutas (platform + admin.billing.*).
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceManager $invoices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:issued,paid,void,overdue'], 'client_id' => ['nullable', 'integer']]);

        $invoices = $this->query()
            ->with('payments')
            ->when($request->filled('client_id'), fn (Builder $q) => $q->where('tenant_id', $request->integer('client_id')))
            ->when($request->input('status') === 'overdue', fn (Builder $q) => $q->where('status', InvoiceStatus::Issued)->where('due_date', '<', today()))
            ->when(in_array($request->input('status'), ['issued', 'paid', 'void'], true), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return InvoiceResource::collection($invoices);
    }

    public function show(int $invoice): InvoiceResource
    {
        return new InvoiceResource($this->query()->with('payments')->findOrFail($invoice));
    }

    public function store(IssueInvoiceRequest $request, Tenant $client): JsonResponse
    {
        $invoice = $this->invoices->issue($client, (string) $request->input('subtotal'), $request->string('description')->toString());

        return (new InvoiceResource($invoice->load('payments')))->response()->setStatusCode(201);
    }

    public function recordPayment(RecordPaymentRequest $request, int $invoice): JsonResponse
    {
        $payment = $this->invoices->recordPayment($this->query()->findOrFail($invoice), $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function void(Request $request, int $invoice): InvoiceResource
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return new InvoiceResource($this->invoices->void($this->query()->findOrFail($invoice), $request->string('reason')->toString())->load('payments'));
    }

    /** @return Builder<Invoice> */
    private function query(): Builder
    {
        return Invoice::query()->withoutGlobalScope(TenantScope::class);
    }
}
