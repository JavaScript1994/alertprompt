<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RoleScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Api\Admin\Roles\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Authorization\RoleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Roles globales de la plataforma. Sin TenantScope a propósito: los roles no
 * pertenecen a un cliente, y estas rutas solo las alcanza el tenant de la
 * plataforma (middleware `platform` + `admin.roles.*`).
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleManager $roles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = $this->query()
            ->when($request->filled('scope'), fn (Builder $query) => $query->where('scope', RoleScope::from($request->string('scope')->toString())))
            ->orderByDesc('is_system')
            ->orderBy('scope')
            ->orderBy('label')
            ->get();

        return RoleResource::collection($roles);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($this->query()->with('permissions')->findOrFail($role->id));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roles->create(
            $request->string('label')->toString(),
            $request->input('description'),
            RoleScope::from($request->string('scope')->toString()),
            $request->input('permissions'),
        );

        // Se relee con los conteos; el modelo releído ya no sabe que es nuevo,
        // así que el 201 va explícito.
        return (new RoleResource($this->query()->with('permissions')->findOrFail($role->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->roles->update(
            $role,
            $request->string('label')->toString(),
            $request->input('description'),
            $request->input('permissions'),
        );

        return new RoleResource($this->query()->with('permissions')->findOrFail($role->id));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roles->delete($role);

        return response()->json(status: 204);
    }

    /**
     * Cuenta usuarios de todos los tenants: la relación users() de Spatie
     * filtra por el tenant activo y aquí necesitamos el total global.
     *
     * @return Builder<Role>
     */
    private function query(): Builder
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->withCount('permissions')
            ->addSelect(['users_count' => DB::table('model_has_roles')
                ->selectRaw('count(*)')
                ->whereColumn('model_has_roles.role_id', 'roles.id')]);
    }
}
