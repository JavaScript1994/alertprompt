<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles->map(fn ($role) => [
                'name' => $role->name,
                'label' => $role->label,
            ])->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            // Modo soporte: el panel muestra los datos de este cliente.
            'impersonating' => $this->impersonatedTenant($request),
        ];
    }

    private function impersonatedTenant(Request $request): ?TenantResource
    {
        $tenantId = $request->attributes->get(Impersonation::REQUEST_ATTRIBUTE);

        return $tenantId === null ? null : new TenantResource(Tenant::query()->find($tenantId));
    }
}
