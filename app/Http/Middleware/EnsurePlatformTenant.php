<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas /api/admin/*: solo usuarios del tenant de la plataforma. Además de
 * esto, cada ruta exige su permiso puntual (admin.*).
 */
class EnsurePlatformTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user?->tenant?->is_platform === true, 403);

        return $next($request);
    }
}
