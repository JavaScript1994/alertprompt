<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Users\InviteUserRequest;
use App\Http\Requests\Api\Users\UpdateUserRequest;
use App\Http\Resources\TenantUserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Users\TenantUserManager;
use App\Support\TenantContext;
use App\Support\UserRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Usuarios del tenant activo (el propio, o el del cliente en modo soporte).
 * {user} se resuelve con TenantScope, así que no alcanza usuarios de otro tenant.
 */
class UserController extends Controller
{
    public function __construct(private readonly TenantUserManager $users) {}

    public function index(): AnonymousResourceCollection
    {
        $users = User::query()->orderByRaw('deactivated_at IS NOT NULL')->orderBy('name')->get();
        UserRoles::attach($users);

        return TenantUserResource::collection($users);
    }

    public function roles(): JsonResponse
    {
        $roles = $this->users->assignableRoles($this->tenant());

        return response()->json(['data' => $roles->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label,
            'description' => $role->description,
        ])->values()]);
    }

    public function store(InviteUserRequest $request): JsonResponse
    {
        $user = $this->users->invite(
            $this->tenant(),
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->integer('role_id'),
        );

        return $this->respond($user)->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): TenantUserResource
    {
        $this->users->update($request->user(), $user, $request->string('name')->toString(), $request->integer('role_id'));

        return $this->respond($user);
    }

    public function deactivate(Request $request, User $user): TenantUserResource
    {
        return $this->respond($this->users->deactivate($request->user(), $user));
    }

    public function reactivate(User $user): TenantUserResource
    {
        return $this->respond($this->users->reactivate($user));
    }

    public function resendInvitation(User $user): JsonResponse
    {
        $this->users->resendInvitation($user);

        return response()->json(status: 204);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(TenantContext::id());
    }

    private function respond(User $user): TenantUserResource
    {
        $user->refresh();
        UserRoles::attach([$user]);

        return new TenantUserResource($user);
    }
}
