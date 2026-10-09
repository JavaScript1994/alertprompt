<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En modo soporte, toda escritura sobre los datos del cliente queda
 * registrada con método, ruta y resultado.
 */
class AuditImpersonatedWrites
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $tenantId = $request->attributes->get(Impersonation::REQUEST_ATTRIBUTE);

        if ($tenantId !== null && ! $request->isMethodSafe() && ! $request->is('api/admin/*')) {
            $this->audit->record('impersonation.request', (int) $tenantId, metadata: [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
