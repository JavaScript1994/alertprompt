<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use App\Models\PlanChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlanChangeRequest
 */
class PlanChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $names = Plan::query()->whereIn('key', array_filter([$this->current_plan, $this->requested_plan]))->pluck('name', 'key');

        return [
            'id' => $this->id,
            'current_plan' => $this->current_plan,
            'current_plan_name' => $names[$this->current_plan] ?? $this->current_plan,
            'requested_plan' => $this->requested_plan,
            'requested_plan_name' => $names[$this->requested_plan] ?? $this->requested_plan,
            'status' => $this->status,
            'comment' => $this->comment,
            'requested_by' => $this->requester?->name,
            'decided_by' => $this->decider?->name,
            'decided_at' => $this->decided_at,
            'decision_note' => $this->decision_note,
            'effective_from' => $this->effective_from?->toDateString(),
            'tenant' => $this->when($request->is('api/admin/*'), fn () => ['id' => $this->tenant_id, 'name' => $this->tenant?->name]),
            'created_at' => $this->created_at,
        ];
    }
}
