<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fija el tenant activo de la request: lo usan TenantScope (aislamiento de
 * datos) y Spatie Permission (roles asignados por tenant). Hay un solo punto
 * que los fija para que no se desincronicen.
 *
 * Normalmente ambos son el mismo tenant. Difieren cuando el equipo de la
 * plataforma supervisa o da soporte a un cliente: los DATOS son del cliente,
 * pero los PERMISOS siguen siendo los del usuario en la plataforma.
 */
final class TenantContext
{
    public static function set(int $dataTenantId, ?int $permissionsTenantId = null): void
    {
        app()->instance('current_tenant_id', $dataTenantId);
        setPermissionsTeamId($permissionsTenantId ?? $dataTenantId);
    }

    public static function id(): ?int
    {
        return app('current_tenant_id');
    }

    /**
     * Ejecuta $callback con los roles/permisos de Spatie apuntando a
     * $tenantId (p. ej. para asignar un rol en otro tenant) y restaura.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withPermissionsOf(int $tenantId, callable $callback): mixed
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}
