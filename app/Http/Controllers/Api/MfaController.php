<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AuthEventType;
use App\Enums\SensitiveAction;
use App\Exceptions\Mfa\MfaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mfa\ConfirmRequest;
use App\Http\Requests\Api\Mfa\RecoveryRequest;
use App\Http\Requests\Api\Mfa\VerifyRequest;
use App\Http\Resources\MfaSetupResource;
use App\Http\Resources\MfaStatusResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Mfa\AuthEventRecorder;
use App\Services\Mfa\ChallengeTokenService;
use App\Services\Mfa\MfaLoginService;
use App\Services\Mfa\MfaService;
use App\Services\Mfa\MfaThrottle;
use App\Services\Mfa\RecoveryCodeService;
use App\Services\Mfa\SecurityNotifier;
use App\Services\Mfa\SensitiveActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function __construct(
        private readonly MfaService $mfa,
        private readonly MfaThrottle $throttle,
        private readonly AuthEventRecorder $events,
    ) {}

    public function status(Request $request): MfaStatusResource
    {
        return new MfaStatusResource($request->user());
    }

    /**
     * Genera un secreto pendiente y su QR. Con MFA ya activo (cambio de
     * dispositivo) exige haber entrado con un respaldo hace poco o una
     * re-autenticación `change_authenticator`.
     */
    public function setup(Request $request, MfaLoginService $login, SensitiveActionService $actions): MfaSetupResource
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasMfaEnabled()
            && ! $login->inReenrollWindow($request)
            && ! $actions->isConfirmed($user, SensitiveAction::ChangeAuthenticator)) {
            throw MfaException::reauthRequired(SensitiveAction::ChangeAuthenticator);
        }

        return new MfaSetupResource($this->mfa->startEnrollment($user));
    }

    /** Confirma con el primer código. Devuelve los códigos de recuperación, una vez. */
    public function confirm(ConfirmRequest $request, MfaLoginService $login): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->throttle->attempt($this->throttle->forConfirm($user->id));

        $codes = $this->mfa->confirmEnrollment($user, $request->string('code')->toString());

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'El código no es válido. Revisa la hora de tu teléfono e intenta con el código actual.']);
        }

        // Acaba de demostrar el segundo factor en esta sesión.
        $this->mfa->markSessionVerified($request->session(), $user);
        $request->session()->forget(MfaLoginService::REENROLL_SESSION_KEY);

        return response()->json(['data' => [
            'recovery_codes' => $codes,
            'user' => new UserResource($user->load('tenant')),
        ]]);
    }

    /** Segundo paso del login con la app de autenticación. */
    public function verify(VerifyRequest $request, ChallengeTokenService $challenges, MfaLoginService $login): UserResource
    {
        $token = $request->string('challenge_token')->toString();
        $user = $challenges->resolve($token, (string) $request->ip()) ?? throw MfaException::invalidChallenge();

        $this->throttle->attempt($this->throttle->forVerify($user->id, (string) $request->ip()));

        if (! $this->mfa->verifyUserCode($user, $request->string('code')->toString())) {
            $this->events->record(AuthEventType::MfaFailed, $user, ['method' => 'totp']);

            throw ValidationException::withMessages(['code' => 'El código no es válido.']);
        }

        if (! $challenges->consume($token)) {
            throw MfaException::invalidChallenge();
        }

        $login->startSession($request, $user, 'totp');
        $this->events->record(AuthEventType::MfaSuccess, $user, ['method' => 'totp']);

        return new UserResource($user->load('tenant'));
    }

    /** Segundo paso con un código de recuperación (perdí mi dispositivo). */
    public function recovery(
        RecoveryRequest $request,
        ChallengeTokenService $challenges,
        MfaLoginService $login,
        RecoveryCodeService $codes,
        SecurityNotifier $notifier,
    ): UserResource {
        $token = $request->string('challenge_token')->toString();
        $user = $challenges->resolve($token, (string) $request->ip()) ?? throw MfaException::invalidChallenge();

        $this->throttle->attempt($this->throttle->forRecovery($user->id));

        if (! $codes->consume($user, $request->string('recovery_code')->toString())) {
            $this->events->record(AuthEventType::MfaFailed, $user, ['method' => 'recovery_code']);

            throw ValidationException::withMessages(['recovery_code' => 'El código de recuperación no es válido o ya se usó.']);
        }

        if (! $challenges->consume($token)) {
            throw MfaException::invalidChallenge();
        }

        $login->startSession($request, $user, 'recovery_code');
        $this->events->record(AuthEventType::RecoveryCodeUsed, $user, ['remaining' => $codes->remaining($user)]);
        $notifier->backupMethodUsed($user, AuthEventType::RecoveryCodeUsed);

        return new UserResource($user->load('tenant'));
    }

    public function regenerateRecoveryCodes(Request $request, RecoveryCodeService $codes): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $plain = $codes->generate($user);
        $this->events->record(AuthEventType::RecoveryCodesRegenerated, $user);

        return response()->json(['data' => ['recovery_codes' => $plain]]);
    }

    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->mfa->disable($user);
        $request->session()->forget(MfaService::SESSION_KEY);

        return response()->json(status: 204);
    }

    /**
     * Un administrador restablece el MFA de un usuario de su cuenta. {user}
     * se resuelve con TenantScope: no alcanza usuarios de otro tenant.
     */
    public function reset(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($actor->id === $user->id) {
            throw ValidationException::withMessages(['user' => 'Para tu propio usuario usa "Desactivar" en Seguridad.']);
        }

        $this->mfa->resetFor($user, $actor);

        return response()->json(status: 204);
    }
}
