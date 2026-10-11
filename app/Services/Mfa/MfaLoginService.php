<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Enums\AuthEventType;
use App\Models\User;
use App\Services\Impersonation;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Login en dos pasos. Con MFA enrolado, la contraseña NO abre sesión: entrega
 * un challenge_token y la sesión se crea al validar el segundo factor. Sin MFA
 * enrolado se abre una sesión restringida (EnsureMfaVerified solo deja
 * enrolar) o, dentro del plazo de gracia, una sesión normal.
 */
class MfaLoginService
{
    /** Minutos para configurar un dispositivo nuevo tras entrar con un respaldo. */
    private const REENROLL_MINUTES = 15;

    public const REENROLL_SESSION_KEY = 'mfa.reenroll_until';

    public function __construct(
        private readonly MfaService $mfa,
        private readonly AuthEventRecorder $events,
    ) {}

    /** Valida contraseña y estado del usuario/tenant. No abre sesión. */
    public function checkPassword(string $email, string $password): User
    {
        $provider = Auth::guard('web')->getProvider();

        /** @var User|null $user */
        $user = $provider->retrieveByCredentials(['email' => $email]);

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $password])) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña son incorrectos.']);
        }

        $user->load('tenant');

        $blocked = match (true) {
            ! $user->tenant->status->canSignIn() => 'La cuenta de tu empresa está suspendida. Comunícate con soporte.',
            ! $user->isActive() => 'Tu usuario fue desactivado. Pide acceso al administrador de tu cuenta.',
            default => null,
        };

        if ($blocked !== null) {
            throw ValidationException::withMessages(['email' => $blocked]);
        }

        $this->events->record(AuthEventType::Login, $user, ['mfa_enabled' => $user->hasMfaEnabled()]);

        return $user;
    }

    /**
     * Abre la sesión. $verifiedBy: método del segundo factor que se acaba de
     * validar (null = solo contraseña, usuario sin MFA).
     */
    public function startSession(Request $request, User $user, ?string $verifiedBy = null): void
    {
        Auth::guard('web')->login($user);

        // ID de sesión nuevo tras autenticar (fijación de sesión).
        $request->session()->regenerate();
        $request->session()->forget([Impersonation::SESSION_KEY, MfaService::SESSION_KEY, self::REENROLL_SESSION_KEY]);

        if ($verifiedBy !== null) {
            $this->mfa->markSessionVerified($request->session(), $user);
        }

        // Entró sin su dispositivo: puede configurar uno nuevo sin re-autenticar.
        if (in_array($verifiedBy, ['recovery_code', 'email_backup'], true)) {
            $request->session()->put(self::REENROLL_SESSION_KEY, now()->addMinutes(self::REENROLL_MINUTES)->getTimestamp());
        }

        if (! $user->hasMfaEnabled()) {
            $this->mfa->startGracePeriod($user);
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        // El middleware corrió antes del login (sin usuario): fijamos el
        // tenant aquí para que roles y permisos de la respuesta salgan bien.
        TenantContext::set($user->tenant_id);
    }

    public function inReenrollWindow(Request $request): bool
    {
        return (int) $request->session()->get(self::REENROLL_SESSION_KEY, 0) > now()->getTimestamp();
    }
}
