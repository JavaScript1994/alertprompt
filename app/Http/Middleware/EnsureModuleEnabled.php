<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Modules\TenantModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `module:csv_import` — la ruta exige que el tenant activo tenga el módulo. */
class EnsureModuleEnabled
{
    public function __construct(private readonly TenantModules $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! $this->modules->isEnabled($module)) {
            $label = $this->modules->catalog()[$module]['label'] ?? $module;
            abort(403, "Tu plan no incluye el módulo «{$label}». Comunícate con AlertPrompt para activarlo.");
        }

        return $next($request);
    }
}
