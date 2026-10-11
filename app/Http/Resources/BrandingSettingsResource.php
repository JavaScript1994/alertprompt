<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use App\Services\Branding\BrandingService;
use App\Support\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class BrandingSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'login_url' => TenantDomain::urlFor($this->resource),
            'logo_url' => app(BrandingService::class)->logoUrl($this->resource, public: false),
            'color' => $this->brand_color,
            'title' => $this->brand_title,
            'default_color' => config('branding.default_color'),
        ];
    }
}
