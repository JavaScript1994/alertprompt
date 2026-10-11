<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Enums\AuthEventType;
use App\Enums\SensitiveAction;
use App\Models\SensitiveActionConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

/**
 * Re-autenticación (contraseña + TOTP) para acciones sensibles. Cada
 * confirmación vale para UNA acción, desde la misma IP, durante
 * `mfa.reauth_ttl` segundos.
 */
class SensitiveActionService
{
    public function __construct(
        private readonly MfaService $mfa,
        private readonly AuthEventRecorder $events,
    ) {}

    public function reauthenticate(User $user, SensitiveAction $action, string $password, string $code): bool
    {
        // Las dos verificaciones siempre: no revelar cuál de las dos falló.
        $passwordOk = Hash::check($password, $user->password);
        $codeOk = $user->hasMfaEnabled() && $this->mfa->verifyUserCode($user, $code);

        if (! $passwordOk || ! $codeOk) {
            $this->events->record(AuthEventType::ReauthFailed, $user, ['action' => $action->value]);

            return false;
        }

        SensitiveActionConfirmation::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'confirmed_at' => now(),
            'expires_at' => now()->addSeconds((int) config('mfa.reauth_ttl', 900)),
            'ip' => Request::ip(),
        ]);

        $this->events->record(AuthEventType::ReauthSuccess, $user, ['action' => $action->value]);

        return true;
    }

    public function isConfirmed(User $user, SensitiveAction $action): bool
    {
        return SensitiveActionConfirmation::query()
            ->where('user_id', $user->id)
            ->where('action', $action)
            ->where('ip', Request::ip())
            ->where('expires_at', '>', now())
            ->exists();
    }
}
