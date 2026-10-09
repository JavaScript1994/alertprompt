<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Responde lo mismo exista o no el correo: no revela qué cuentas existen.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker('users')->sendResetLink($request->safe()->only('email'));

        return response()->json([
            'message' => 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $broker = $request->boolean('invite') ? 'invitations' : 'users';

        $status = Password::broker($broker)->reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'El enlace no es válido o ya venció. Pide uno nuevo.',
            ]);
        }

        return response()->json(['message' => 'Tu contraseña quedó guardada. Ya puedes iniciar sesión.']);
    }
}
