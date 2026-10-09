<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Enums\RoleScope;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición y baja de roles globales (tenant_id null) desde el panel de
 * la plataforma. Las reglas de negocio viven aquí; la forma de los datos se
 * valida en los Form Requests.
 */
class RoleManager
{
    private const GUARD = 'web';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<string>  $permissions */
    public function create(string $label, ?string $description, RoleScope $scope, array $permissions): Role
    {
        return DB::transaction(function () use ($label, $description, $scope, $permissions) {
            $role = Role::query()->create([
                'tenant_id' => null,
                'name' => $this->uniqueName($label),
                'guard_name' => self::GUARD,
                'label' => $label,
                'description' => $description,
                'scope' => $scope,
                'is_system' => false,
            ]);

            $role->syncPermissions($permissions);
            $this->audit->record('role.created', subject: $role, metadata: ['permissions' => $permissions]);

            return $role;
        });
    }

    /** @param  list<string>  $permissions */
    public function update(Role $role, string $label, ?string $description, array $permissions): Role
    {
        $this->ensureEditable($role);

        return DB::transaction(function () use ($role, $label, $description, $permissions) {
            // `name` no cambia: es la clave que usa el código y los pivotes.
            $before = $role->permissions->pluck('name')->all();
            $role->update(['label' => $label, 'description' => $description]);
            $role->syncPermissions($permissions);

            $this->audit->record('role.updated', subject: $role, metadata: [
                'added' => array_values(array_diff($permissions, $before)),
                'removed' => array_values(array_diff($before, $permissions)),
            ]);

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        $this->ensureEditable($role);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Los roles de sistema no se eliminan; puedes editar sus permisos.',
            ]);
        }

        $assigned = DB::table('model_has_roles')->where('role_id', $role->id)->count();

        if ($assigned > 0) {
            throw ValidationException::withMessages([
                'role' => "El rol está asignado a {$assigned} usuario(s). Reasígnalos antes de eliminarlo.",
            ]);
        }

        $this->audit->record('role.deleted', metadata: ['name' => $role->name, 'label' => $role->label]);
        $role->delete();
    }

    private function ensureEditable(Role $role): void
    {
        if ($role->isLocked()) {
            throw ValidationException::withMessages([
                'role' => 'El rol de dueño de la plataforma siempre tiene todos los permisos y no se modifica.',
            ]);
        }
    }

    private function uniqueName(string $label): string
    {
        $base = Str::slug($label) ?: 'rol';
        $name = $base;
        $suffix = 2;

        while (Role::query()->whereNull('tenant_id')->where('name', $name)->exists()) {
            $name = "{$base}-{$suffix}";
            $suffix++;
        }

        return $name;
    }
}
