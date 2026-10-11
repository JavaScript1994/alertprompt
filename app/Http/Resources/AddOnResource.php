<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddOn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AddOn
 */
class AddOnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'module' => $this->module,
            'monthly_price' => $this->monthly_price !== null ? (string) $this->monthly_price : null,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'active_contracts_count' => $this->when(isset($this->active_contracts_count), fn () => (int) $this->active_contracts_count),
            'interests_count' => $this->when(isset($this->interests_count), fn () => (int) $this->interests_count),
        ];
    }
}
