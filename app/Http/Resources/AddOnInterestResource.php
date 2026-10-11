<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddOnInterest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AddOnInterest
 */
class AddOnInterestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant' => ['id' => $this->tenant_id, 'name' => $this->tenant?->name, 'plan' => $this->tenant?->plan],
            'user' => $this->user?->name,
            'created_at' => $this->created_at,
        ];
    }
}
