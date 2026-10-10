<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Membership;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $forPlatform = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'plan' => $this->plan,
            'plan_name' => Plan::query()->where('key', $this->plan)->value('name') ?? $this->plan,
            'status' => $this->status,
            'billing_cycle' => $this->billing_cycle,
            'price' => (string) $this->price,
            'currency' => $this->currency,
            'starts_at' => $this->starts_at->toDateString(),
            'ends_at' => $this->ends_at->toDateString(),
            'quotas' => $this->quotas,
            'contract_reference' => $this->contract_reference,
            'notes' => $this->when($forPlatform, $this->notes),
            'created_by' => $this->when($forPlatform, fn () => $this->creator?->name),
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at,
        ];
    }
}
