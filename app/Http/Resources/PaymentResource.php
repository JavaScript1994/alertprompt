<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_code' => $this->whenLoaded('invoice', fn () => $this->invoice->code()),
            'amount' => (string) $this->amount,
            'method' => $this->method,
            'method_label' => $this->method->label(),
            'reference' => $this->provider_reference,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'notes' => $this->when($request->is('api/admin/*'), $this->notes),
        ];
    }
}
