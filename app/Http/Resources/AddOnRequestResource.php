<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddOnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AddOnRequest
 */
class AddOnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'add_on' => [
                'id' => $this->add_on_id,
                'key' => $this->addOn?->key,
                'name' => $this->addOn?->name,
                'monthly_price' => $this->addOn?->monthly_price !== null ? (string) $this->addOn->monthly_price : null,
            ],
            'status' => $this->status->value,
            'comment' => $this->comment,
            'requested_by' => $this->requester?->name,
            'decided_by' => $this->decider?->name,
            'decided_at' => $this->decided_at,
            'decision_note' => $this->decision_note,
            'tenant' => ['id' => $this->tenant_id, 'name' => $this->tenant?->name],
            'created_at' => $this->created_at,
        ];
    }
}
