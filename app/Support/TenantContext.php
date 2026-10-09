<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fija el tenant activo de la request: lo usan TenantScope (aislamiento de
 * datos) y Spatie Permission (roles asignados por tenant). Siempre deben
 * moverse juntos; por eso hay un solo punto que los fija.
 */
final class TenantContext
{
    public static function set(int $tenantId): void
    {
        app()->instance('current_tenant_id', $tenantId);
        setPermissionsTeamId($tenantId);
    }
}
