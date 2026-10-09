<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\RoleScope;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Clients\ClientManager;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Usuarios de un tenant gestionados por su propio administrador (o por el
 * soporte en modo soporte). Reglas: solo roles del panel que corresponde,
 * nadie se desactiva a sí mismo y siempre queda al menos un usuario activo
 * capaz de gestionar usuarios.
 */
class TenantUserManager
{
    private const MANAGE_PERMISSION = 'users.manage';

    public function __construct(
        private readonly ClientManager $clients,
        private readonly AuditLogger $audit,
    ) {}

    /** Roles que se pueden asignar en este tenant. */
    public function assignableRoles(Tenant $tenant): Collection
    {
        return Role::query()
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->where('scope', $tenant->is_platform ? RoleScope::Platform : RoleScope::Client)
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->get();
    }

    public function invite(Tenant $tenant, string $name, string $email, int $roleId): User
    {
        $role = $this->assignableRole($tenant, $roleId);

        $user = DB::transaction(function () use ($tenant, $name, $email, $role) {
            $user = $this->clients->createUser($tenant, $name, $email, $role->name);
            $this->audit->record('user.invited', $tenant->id, $user, ['email' => $email, 'role' => $role->name]);

            return $user;
        });

        $this->clients->invite($user, $tenant);

        return $user;
    }

    public function update(User $actor, User $user, string $name, int $roleId): User
    {
        $tenant = $user->tenant;
        $role = $this->assignableRole($tenant, $roleId);

        DB::transaction(function () use ($actor, $user, $name, $role, $tenant) {
            $user->update(['name' => $name]);

            $current = $this->roleNamesOf($user);
            if ($current !== [$role->name]) {
                if ($user->is($actor) && ! $this->roleCanManage($role)) {
                    throw ValidationException::withMessages([
                        'role_id' => 'No puedes quitarte a ti mismo el permiso de gestionar usuarios.',
                    ]);
                }

                TenantContext::withPermissionsOf($tenant->id, fn () => $user->syncRoles([$role->name]));
                $this->ensureSomeoneCanManage($tenant);
                $this->audit->record('user.role_changed', $tenant->id, $user, ['from' => $current, 'to' => $role->name]);
            }
        });

        return $user;
    }

    public function deactivate(User $actor, User $user): User
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => 'No puedes desactivar tu propio usuario.']);
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['deactivated_at' => now()])->save();
            $this->ensureSomeoneCanManage($user->tenant);
            $this->audit->record('user.deactivated', $user->tenant_id, $user);
        });

        return $user;
    }

    public function reactivate(User $user): User
    {
        $user->forceFill(['deactivated_at' => null])->save();
        $this->audit->record('user.reactivated', $user->tenant_id, $user);

        return $user;
    }

    public function resendInvitation(User $user): void
    {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages(['user' => 'Este usuario ya creó su contraseña.']);
        }

        $this->clients->invite($user, $user->tenant);
        $this->audit->record('user.invitation_resent', $user->tenant_id, $user);
    }

    private function assignableRole(Tenant $tenant, int $roleId): Role
    {
        $role = $this->assignableRoles($tenant)->firstWhere('id', $roleId);

        if ($role === null) {
            throw ValidationException::withMessages(['role_id' => 'Ese rol no se puede asignar en esta cuenta.']);
        }

        return $role;
    }

    /** @return list<string> */
    private function roleNamesOf(User $user): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.tenant_id', $user->tenant_id)
            ->pluck('roles.name')
            ->all();
    }

    private function roleCanManage(Role $role): bool
    {
        return $role->permissions()->where('name', self::MANAGE_PERMISSION)->exists();
    }

    /** Lanza si el cambio deja la cuenta sin nadie activo que gestione usuarios. */
    private function ensureSomeoneCanManage(Tenant $tenant): void
    {
        $managers = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->on('model_has_roles.tenant_id', '=', 'users.tenant_id')
                    ->where('model_has_roles.model_type', (new User)->getMorphClass());
            })
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('users.tenant_id', $tenant->id)
            ->whereNull('users.deactivated_at')
            ->where('permissions.name', self::MANAGE_PERMISSION)
            ->distinct()
            ->count('users.id');

        if ($managers === 0) {
            throw ValidationException::withMessages([
                'user' => 'La cuenta debe conservar al menos un usuario activo que pueda gestionar usuarios.',
            ]);
        }
    }
}
