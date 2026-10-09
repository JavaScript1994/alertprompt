<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * "Entrar al panel de un cliente" para dar soporte. Solo cambia de qué
 * tenant son los DATOS; los permisos siguen siendo los del usuario en la
 * plataforma. Todo inicio, fin y escritura queda en audit_logs.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonating_tenant_id';

    public const REQUEST_ATTRIBUTE = 'impersonated_tenant_id';

    public function __construct(private readonly AuditLogger $audit) {}

    public function start(Request $request, Tenant $client): void
    {
        $request->session()->put(self::SESSION_KEY, $client->id);

        $this->audit->record('impersonation.started', $client->id, $client);
    }

    public function stop(Request $request): void
    {
        $tenantId = $request->session()->pull(self::SESSION_KEY);

        if ($tenantId !== null) {
            $this->audit->record('impersonation.stopped', (int) $tenantId);
        }
    }

    /**
     * Tenant que el usuario está viendo en modo soporte, o null. Se vuelve a
     * validar en cada request: si el usuario perdió el permiso o el cliente
     * ya no existe, el modo soporte se corta solo.
     */
    public function activeTenantId(Request $request, User $user): ?int
    {
        if (! $request->hasSession() || ! $user->tenant->is_platform) {
            return null;
        }

        $tenantId = $request->session()->get(self::SESSION_KEY);

        if ($tenantId === null) {
            return null;
        }

        $valid = $user->can('admin.impersonate.use')
            && Tenant::query()->whereKey($tenantId)->where('is_platform', false)->exists();

        if (! $valid) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return (int) $tenantId;
    }
}
