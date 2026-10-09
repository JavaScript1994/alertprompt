<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supervisión de solo lectura: reutiliza los controladores del panel de
 * cliente con los DATOS del cliente {client} y los PERMISOS del usuario de la
 * plataforma. Solo se monta sobre rutas GET.
 */
class SuperviseClient
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Tenant $client */
        $client = $request->route('client');

        abort_unless($request->isMethodSafe(), 405);

        TenantContext::set($client->id, $request->user()->tenant_id);

        return $next($request);
    }
}
