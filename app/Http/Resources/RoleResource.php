<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'scope' => $this->scope,
            'is_system' => $this->is_system,
            'is_locked' => $this->isLocked(),
            'users_count' => (int) ($this->users_count ?? 0),
            'permissions_count' => (int) ($this->permissions_count ?? $this->permissions->count()),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->sort()->values()),
        ];
    }
}
