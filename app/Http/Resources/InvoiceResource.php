<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\SunatStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paid = (float) ($this->paid_total ?? $this->paidAmount());

        return [
            'id' => $this->id,
            'code' => $this->code(),
            'document_type' => $this->document_type,
            // Hasta que SUNAT lo acepte, es un documento interno.
            'is_electronic' => $this->sunat_status === SunatStatus::Accepted,
            'sunat_status' => $this->sunat_status,
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'igv' => (string) $this->igv,
            'total' => (string) $this->total,
            'paid' => number_format($paid, 2, '.', ''),
            'balance' => number_format(max(0, (float) $this->total - $paid), 2, '.', ''),
            'description' => $this->description,
            'customer' => $this->customer,
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'pdf_url' => $this->pdf_url,
            'paid_at' => $this->paid_at,
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
            'tenant' => $this->when($request->is('api/admin/*'), fn () => ['id' => $this->tenant_id, 'name' => $this->customer['name'] ?? null]),
            'payments' => $this->whenLoaded('payments', fn () => PaymentResource::collection($this->payments)),
        ];
    }
}
