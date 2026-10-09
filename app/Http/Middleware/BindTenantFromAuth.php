<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BindTenantFromAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user !== null) {
            // Un tenant suspendido pierde el acceso también en sesiones ya abiertas.
            abort_unless($user->tenant->status->canSignIn(), 403, 'La cuenta de tu empresa está suspendida.');

            TenantContext::set($user->tenant_id);
        }

        return $next($request);
    }
}
