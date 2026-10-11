<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use App\Services\Branding\BrandingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lo que el login necesita saber de su dominio, antes de iniciar sesión.
 * Solo datos públicos: nombre, subdominio y la marca si el plan la incluye.
 *
 * @property array{tenant: ?Tenant, unknown: bool} $resource
 */
class PublicBrandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->resource['tenant'];
        $branding = app(BrandingService::class);
        $active = $tenant !== null && $branding->isActive($tenant);

        return [
            'unknown' => $this->resource['unknown'],
            'tenant' => $tenant === null ? null : ['name' => $tenant->name, 'slug' => $tenant->slug],
            'branded' => $active,
            'logo_url' => $active ? $branding->logoUrl($tenant, public: true) : null,
            'color' => $active ? $tenant->brand_color : null,
            'title' => $active ? $tenant->brand_title : null,
        ];
    }
}
