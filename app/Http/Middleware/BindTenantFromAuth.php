<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BindTenantFromAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = Auth::user()) {
            app()->instance('current_tenant_id', $user->tenant_id);
        }

        return $next($request);
    }
}
