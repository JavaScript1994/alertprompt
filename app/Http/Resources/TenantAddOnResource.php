<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TenantAddOn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato de un servicio adicional (ficha del cliente en administración).
 *
 * @mixin TenantAddOn
 */
class TenantAddOnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'add_on' => ['id' => $this->add_on_id, 'key' => $this->addOn?->key, 'name' => $this->addOn?->name, 'module' => $this->addOn?->module],
            'status' => $this->status->value,
            'monthly_price' => (string) $this->monthly_price,
            'starts_at' => $this->starts_at->toDateString(),
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'activated_by' => $this->activator?->name,
            'cancelled_by' => $this->canceller?->name,
        ];
    }
}
