<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $remember = $request->boolean('remember', true);
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, remember: $remember)) {
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña son incorrectos.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user()->load('tenant');

        return new UserResource($user);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    public function user(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user()->load('tenant');

        return new UserResource($user);
    }
}
