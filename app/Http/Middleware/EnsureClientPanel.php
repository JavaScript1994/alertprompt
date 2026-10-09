<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas del panel de cliente (mensajería, reportes, facturación, cuenta).
 * La administración general no tiene panel de mensajería propio: solo las
 * alcanza en modo soporte, con los datos del cliente que está atendiendo.
 */
class EnsureClientPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->tenant?->is_platform && ! $request->attributes->has(Impersonation::REQUEST_ATTRIBUTE)) {
            abort(403, 'La administración general no tiene panel de mensajería propio. Entra al panel de un cliente desde Clientes.');
        }

        return $next($request);
    }
}
