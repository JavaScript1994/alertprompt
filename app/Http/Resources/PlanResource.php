<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'monthly_price' => $this->monthly_price !== null ? (string) $this->monthly_price : null,
            'quotas' => [
                'whatsapp' => $this->quotas['whatsapp'] ?? null,
                'sms' => $this->quotas['sms'] ?? null,
                'email' => $this->quotas['email'] ?? null,
            ],
            'modules' => $this->modules ?? [],
            'is_public' => $this->is_public,
            'is_active' => $this->is_active,
            'clients_count' => $this->when(isset($this->clients_count), fn () => (int) $this->clients_count),
            'sort' => $this->sort,
        ];
    }
}
