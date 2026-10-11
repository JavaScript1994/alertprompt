<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddOn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Servicio adicional visto por un cliente (ver ClientAddOnCatalog).
 *
 * @mixin AddOn
 */
class ClientAddOnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contract = $this->getRelation('contract');
        $pending = $this->getRelation('pendingRequest');
        $decision = $this->getRelation('lastDecision');

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'module' => $this->module,
            'monthly_price' => $this->monthly_price !== null ? (string) $this->monthly_price : null,
            'state' => $this->client_state,
            'interested' => (bool) $this->interested,
            'contract' => $contract ? [
                'id' => $contract->id,
                'monthly_price' => (string) $contract->monthly_price,
                'starts_at' => $contract->starts_at->toDateString(),
            ] : null,
            'pending_request' => $pending ? [
                'id' => $pending->id,
                'comment' => $pending->comment,
                'requested_by' => $pending->requester?->name,
                'created_at' => $pending->created_at->toIso8601String(),
            ] : null,
            'last_decision' => $decision ? [
                'status' => $decision->status->value,
                'decision_note' => $decision->decision_note,
                'decided_at' => $decision->decided_at?->toIso8601String(),
            ] : null,
        ];
    }
}
