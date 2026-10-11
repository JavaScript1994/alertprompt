<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Users\ChangeUserEmailRequest;
use App\Http\Requests\Api\Users\InviteUserRequest;
use App\Http\Requests\Api\Users\UpdatePersonalDataRequest;
use App\Http\Requests\Api\Users\UpdateUserRoleRequest;
use App\Http\Requests\Api\Users\UploadPhotoRequest;
use App\Http\Resources\TenantUserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Users\TenantUserManager;
use App\Services\Users\UserProfileService;
use App\Support\TenantContext;
use App\Support\UserRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $request->safe()->only(['first_name', 'last_name']),
            $request->string('email')->toString(),
            $request->integer('role_id'),
        );

        return $this->respond($user)->response()->setStatusCode(201);
    }

    /** Datos personales (sin re-autenticación: no cambian accesos). */
    public function update(UpdatePersonalDataRequest $request, User $user, UserProfileService $profiles): TenantUserResource
    {
        $profiles->updatePersonalData($user, $request->validated());

        return $this->respond($user);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): TenantUserResource
    {
        $this->users->updateRole($request->user(), $user, $request->integer('role_id'));

        return $this->respond($user);
    }

    public function uploadPhoto(UploadPhotoRequest $request, User $user, UserProfileService $profiles): TenantUserResource
    {
        $profiles->setPhoto($user, $request->file('photo'));

        return $this->respond($user);
    }

    public function deletePhoto(User $user, UserProfileService $profiles): TenantUserResource
    {
        $profiles->removePhoto($user);

        return $this->respond($user);
    }

    /** Foto de un usuario de la cuenta ({user} con TenantScope). */
    public function photo(User $user, UserProfileService $profiles): StreamedResponse
    {
        return $profiles->photoResponse($user);
    }

    /** Queda pendiente hasta que se confirme desde el correo nuevo. */
    public function changeEmail(ChangeUserEmailRequest $request, User $user, UserProfileService $profiles): TenantUserResource
    {
        $profiles->requestEmailChange($user, $request->string('email')->toString());

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
