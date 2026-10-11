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
            'pending_email' => $this->pending_email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'job_title' => $this->job_title,
            'birth_date' => $this->birth_date?->toDateString(),
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'photo_url' => $this->photoUrl($request),
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

    /** Foto por la API (disco privado). Desde el panel de la plataforma, por la ruta de admin. */
    private function photoUrl(Request $request): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        $version = substr(sha1((string) $this->photo_path), 0, 8);

        return $request->is('api/admin/*')
            ? "/api/admin/clients/{$this->tenant_id}/users/{$this->id}/photo?v={$version}"
            : "/api/users/{$this->id}/photo?v={$version}";
    }
}
