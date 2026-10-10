<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario de un tenant en listados de gestión. Los roles deben venir
 * cargados con UserRoles::attach() (no con la relación de Spatie, que
 * filtra por el tenant activo y aquí puede ser otro).
 *
 * @mixin User
 */
class TenantUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
            ])->values(),
            'email_verified_at' => $this->email_verified_at,
            'mfa_enabled' => $this->resource->hasMfaEnabled(),
            'last_login_at' => $this->last_login_at,
            'deactivated_at' => $this->deactivated_at ?? null,
            'created_at' => $this->created_at,
        ];
    }
}
