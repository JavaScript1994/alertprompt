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
            'is_public' => $this->is_public,
            'sort' => $this->sort,
        ];
    }
}
