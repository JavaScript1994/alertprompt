<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SensitiveAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mfa\ReauthRequest;
use App\Models\User;
use App\Services\Mfa\MfaThrottle;
use App\Services\Mfa\SensitiveActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReauthController extends Controller
{
    /** Contraseña + TOTP: habilita UNA acción sensible por `mfa.reauth_ttl`. */
    public function store(ReauthRequest $request, SensitiveActionService $actions, MfaThrottle $throttle): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action = SensitiveAction::from($request->string('action')->toString());

        if (! $user->hasMfaEnabled()) {
            throw ValidationException::withMessages(['code' => 'Configura la verificación en dos pasos para hacer esta acción.']);
        }

        $throttle->attempt($throttle->forReauth($user->id));

        if (! $actions->reauthenticate($user, $action, $request->string('password')->toString(), $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'La contraseña o el código no son válidos.']);
        }

        return response()->json(['data' => [
            'action' => $action->value,
            'expires_in' => (int) config('mfa.reauth_ttl'),
        ]]);
    }
}
