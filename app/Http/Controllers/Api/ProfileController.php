<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Users\UpdatePersonalDataRequest;
use App\Http\Requests\Api\Users\UploadPhotoRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserProfileService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mi perfil: cada usuario, con cualquier rol, ve y edita sus datos
 * personales y su foto. El correo y el rol los cambia un administrador.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly UserProfileService $profiles) {}

    public function update(UpdatePersonalDataRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($this->profiles->updatePersonalData($user, $request->validated())->load('tenant'));
    }

    public function uploadPhoto(UploadPhotoRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($this->profiles->setPhoto($user, $request->file('photo'))->load('tenant'));
    }

    public function deletePhoto(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($this->profiles->removePhoto($user)->load('tenant'));
    }

    public function photo(Request $request): StreamedResponse
    {
        return $this->profiles->photoResponse($request->user());
    }
}
