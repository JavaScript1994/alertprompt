<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Settings\UpdateBrandingRequest;
use App\Http\Requests\Api\Settings\UploadLogoRequest;
use App\Http\Resources\BrandingSettingsResource;
use App\Models\Tenant;
use App\Services\Branding\BrandingService;
use App\Support\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Configuración → Marca (cliente con el módulo `branding`). */
class BrandingSettingsController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    public function show(): BrandingSettingsResource
    {
        return new BrandingSettingsResource($this->tenant());
    }

    public function update(UpdateBrandingRequest $request): BrandingSettingsResource
    {
        return new BrandingSettingsResource($this->branding->update($this->tenant(), $request->validated()));
    }

    public function uploadLogo(UploadLogoRequest $request): BrandingSettingsResource
    {
        return new BrandingSettingsResource($this->branding->setLogo($this->tenant(), $request->file('logo')));
    }

    public function deleteLogo(): BrandingSettingsResource
    {
        return new BrandingSettingsResource($this->branding->removeLogo($this->tenant()));
    }

    public function logo(): StreamedResponse
    {
        return $this->branding->logoResponse($this->tenant());
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(TenantContext::id());
    }
}
