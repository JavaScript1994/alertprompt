<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Cliente visto desde el panel de la plataforma: perfil + conteos.
 *
 * @mixin Tenant
 */
class ClientResource extends TenantResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'users_count' => (int) ($this->users_count ?? 0),
            'contacts_count' => (int) ($this->contacts_count ?? 0),
            'campaigns_count' => (int) ($this->campaigns_count ?? 0),
            'created_at' => $this->created_at,
        ];
    }
}
