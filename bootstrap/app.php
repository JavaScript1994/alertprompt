<?php

use App\Http\Middleware\BindTenantFromAuth;
use App\Http\Middleware\EnsurePlatformTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Orden explícito: BindTenantFromAuth debe correr DESPUÉS de que la
        // sesión esté disponible (EnsureFrontendRequestsAreStateful) pero
        // ANTES de SubstituteBindings — si no, el route model binding de
        // {contact} resuelve sin tenant_id bindeado y TenantScope no filtra,
        // permitiendo acceso cross-tenant. $middleware->api(append:) no sirve
        // acá: SubstituteBindings ya viene incluido en el array default del
        // grupo 'api', antes de cualquier append.
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            BindTenantFromAuth::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'platform' => EnsurePlatformTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
