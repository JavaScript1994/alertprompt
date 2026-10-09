<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lleva la tabla `permissions` y los roles de sistema al estado de
 * config/permissions.php. Idempotente: se corre en cada deploy.
 */
class SyncPermissions
{
    private const GUARD = 'web';

    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /** @return array{created: int, pruned: int, roles_created: int} */
    public function __invoke(): array
    {
        return DB::transaction(function () {
            $names = $this->registry->all();

            $existing = Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all();
            $missing = array_values(array_diff($names, $existing));
            $stale = array_values(array_diff($existing, $names));

            foreach ($missing as $name) {
                Permission::query()->create(['name' => $name, 'guard_name' => self::GUARD]);
            }

            // Un permiso que ya no está en config no protege nada: se elimina
            // (cascade lo quita de los roles que lo tenían).
            Permission::query()->where('guard_name', self::GUARD)->whereIn('name', $stale)->delete();

            $this->registrar->forgetCachedPermissions();

            $rolesCreated = 0;

            foreach (config('permissions.system_roles', []) as $name => $definition) {
                $role = Role::query()->whereNull('tenant_id')->where('name', $name)->where('guard_name', self::GUARD)->first();

                if ($role === null) {
                    $role = Role::query()->create([
                        'tenant_id' => null,
                        'name' => $name,
                        'guard_name' => self::GUARD,
                        'label' => $definition['label'],
                        'description' => $definition['description'] ?? null,
                        'scope' => RoleScope::from($definition['scope']),
                        'is_system' => true,
                    ]);
                    $role->syncPermissions($this->registry->resolve($definition['permissions']));
                    $rolesCreated++;

                    continue;
                }

                // El dueño siempre conserva todos los permisos, incluidos los
                // que se agreguen al árbol en el futuro.
                if ($definition['permissions'] === '*') {
                    // Bloqueado (no editable): nombre y descripción siguen al config.
                    $role->update(['label' => $definition['label'], 'description' => $definition['description'] ?? null]);
                    $role->syncPermissions($this->registry->all());

                    continue;
                }

                // Los demás roles de sistema se respetan tal como los haya
                // editado el dueño; solo reciben los permisos NUEVOS que su
                // definición incluye (p. ej. un "exportar" recién agregado).
                $newForRole = array_values(array_intersect($missing, $this->registry->resolve($definition['permissions'])));
                if ($newForRole !== []) {
                    $role->givePermissionTo($newForRole);
                }
            }

            $this->registrar->forgetCachedPermissions();

            return ['created' => count($missing), 'pruned' => count($stale), 'roles_created' => $rolesCreated];
        });
    }
}
