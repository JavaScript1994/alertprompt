<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acciones que solo puede ejecutar el propio cliente, nunca el soporte en su
 * nombre (p. ej. disparar una campaña: el envío debe ser decisión del cliente).
 */
class BlockWhenImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->attributes->has(Impersonation::REQUEST_ATTRIBUTE),
            403,
            'En modo soporte no se pueden disparar envíos. Pídeselo al cliente.',
        );

        return $next($request);
    }
}
