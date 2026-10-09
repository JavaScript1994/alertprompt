<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Impersonation;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BindTenantFromAuth
{
    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user !== null) {
            // Un tenant suspendido pierde el acceso también en sesiones ya abiertas.
            abort_unless($user->tenant->status->canSignIn(), 403, 'La cuenta de tu empresa está suspendida.');
            abort_unless($user->isActive(), 403, 'Tu usuario fue desactivado.');

            // Primero el tenant propio: el chequeo de permiso de soporte se
            // hace con los roles del usuario en la plataforma.
            TenantContext::set($user->tenant_id);

            $impersonated = $this->impersonation->activeTenantId($request, $user);

            if ($impersonated !== null) {
                TenantContext::set($impersonated, $user->tenant_id);
                $request->attributes->set(Impersonation::REQUEST_ATTRIBUTE, $impersonated);
            }
        }

        return $next($request);
    }
}
