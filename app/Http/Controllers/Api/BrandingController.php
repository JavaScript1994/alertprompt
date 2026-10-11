<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBrandingResource;
use App\Services\Branding\BrandingService;
use App\Support\TenantDomain;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Público: la marca del login según el subdominio de la petición. */
class BrandingController extends Controller
{
    public function show(Request $request): PublicBrandingResource
    {
        return new PublicBrandingResource([
            'tenant' => TenantDomain::current($request),
            'unknown' => (bool) $request->attributes->get('domain_unknown', false),
        ]);
    }

    public function logo(Request $request, BrandingService $branding): StreamedResponse
    {
        $tenant = TenantDomain::current($request);
        abort_if($tenant === null, 404);

        return $branding->logoResponse($tenant);
    }
}
