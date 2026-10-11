<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\SunatStatus;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditNote
 */
class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $applied = (float) ($this->relationLoaded('applications') ? $this->applications->sum('amount') : $this->appliedAmount());

        return [
            'id' => $this->id,
            'code' => $this->code(),
            // Hasta que SUNAT la acepte, es un documento interno.
            'is_electronic' => $this->sunat_status === SunatStatus::Accepted,
            'issue_date' => $this->issue_date->toDateString(),
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'igv' => (string) $this->igv,
            'total' => (string) $this->total,
            'applied' => number_format($applied, 2, '.', ''),
            'remaining' => number_format(max(0, (float) $this->total - $applied), 2, '.', ''),
            'reason' => $this->reason,
            'lines' => $this->lines,
            'customer' => $this->customer,
            'invoice' => ['id' => $this->invoice_id, 'code' => $this->invoice?->code(), 'document_type' => $this->invoice?->document_type],
            'tenant' => $this->when($request->is('api/admin/*'), fn () => ['id' => $this->tenant_id, 'name' => $this->tenant?->name]),
        ];
    }
}
