<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carga los roles de cada usuario en SU tenant, sin depender del tenant
 * activo de Spatie. Necesario cuando la plataforma lista usuarios de un
 * cliente (el tenant de permisos activo es la plataforma).
 */
final class UserRoles
{
    /** @param  iterable<User>  $users */
    public static function attach(iterable $users): void
    {
        $users = Collection::make($users);

        if ($users->isEmpty()) {
            return;
        }

        $pivots = DB::table('model_has_roles')
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn('model_id', $users->pluck('id'))
            ->get(['model_id', 'role_id', 'tenant_id']);

        $roles = Role::query()->whereIn('id', $pivots->pluck('role_id')->unique())->get()->keyBy('id');

        foreach ($users as $user) {
            $user->setRelation('roles', new EloquentCollection($pivots
                ->where('model_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->map(fn ($pivot) => $roles->get($pivot->role_id))
                ->filter()
                ->values()
                ->all()));
        }
    }
}
