<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En el subdominio de un cliente solo trabajan usuarios de ese cliente.
 * Las cookies de sesión ya son por host (SESSION_DOMAIN vacío); esto lo
 * garantiza aunque alguien configure un dominio de cookie compartido.
 */
class ResolveTenantDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        ['tenant' => $tenant, 'unknown' => $unknown] = TenantDomain::resolve($request);
        $request->attributes->set(TenantDomain::REQUEST_ATTRIBUTE, $tenant);
        $request->attributes->set('domain_unknown', $unknown);

        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && ($unknown || ($tenant !== null && $user->tenant_id !== $tenant->id))) {
            return response()->json([
                'message' => 'Esta dirección es de otra empresa. Entra desde el enlace de la tuya.',
                'code' => 'wrong_domain',
            ], 403);
        }

        return $next($request);
    }
}
