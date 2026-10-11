<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EmailOtpPurpose;
use App\Enums\SensitiveAction;
use App\Exceptions\Mfa\MfaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mfa\ChallengeTokenRequest;
use App\Http\Requests\Api\Mfa\EmailBackupConfirmRequest;
use App\Http\Requests\Api\Mfa\EmailBackupSetupRequest;
use App\Http\Requests\Api\Mfa\EmailBackupVerifyRequest;
use App\Http\Resources\MfaStatusResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Mfa\ChallengeTokenService;
use App\Services\Mfa\EmailBackupService;
use App\Services\Mfa\MfaLoginService;
use App\Services\Mfa\MfaThrottle;
use App\Services\Mfa\SensitiveActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Correo de respaldo: se configura y verifica una vez, y solo se usa en
 * "perdí mi dispositivo". No es un método de login elegible.
 */
class EmailBackupController extends Controller
{
    public function __construct(
        private readonly EmailBackupService $backup,
        private readonly MfaThrottle $throttle,
    ) {}

    /** Envía un código al correo nuevo. Reemplazar uno verificado exige re-autenticación. */
    public function setup(EmailBackupSetupRequest $request, SensitiveActionService $actions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasMfaEnabled() && $user->hasVerifiedEmailBackup()
            && ! $actions->isConfirmed($user, SensitiveAction::ChangeEmailBackup)) {
            throw MfaException::reauthRequired(SensitiveAction::ChangeEmailBackup);
        }

        $this->throttle->attempt($this->throttle->forEmailSetup($user->id));
        $this->backup->startSetup($user, $request->string('email')->toString());

        return response()->json(['message' => 'Te enviamos un código de 6 dígitos a ese correo.'], 202);
    }

    public function confirm(EmailBackupConfirmRequest $request): MfaStatusResource
    {
        /** @var User $user */
        $user = $request->user();
        $this->throttle->attempt($this->throttle->forConfirm($user->id));

        if (! $this->backup->confirmSetup($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => $this->backup->hasUsableCode($user, EmailOtpPurpose::Setup)
                ? 'El código no es válido.'
                : 'El código venció o superaste los intentos. Pide uno nuevo.']);
        }

        return new MfaStatusResource($user->refresh());
    }

    /** Perdí mi dispositivo: código al correo de respaldo verificado. */
    public function challenge(ChallengeTokenRequest $request, ChallengeTokenService $challenges): JsonResponse
    {
        $user = $challenges->resolve($request->string('challenge_token')->toString(), (string) $request->ip())
            ?? throw MfaException::invalidChallenge();

        if (! $user->hasVerifiedEmailBackup()) {
            throw MfaException::emailBackupUnavailable();
        }

        $this->throttle->attempt($this->throttle->forEmailChallenge($user->id));
        $this->backup->sendLoginChallenge($user);

        return response()->json([
            'message' => 'Te enviamos un código a tu correo de respaldo.',
            'email' => MfaStatusResource::mask((string) $user->two_factor_email_backup),
        ], 202);
    }

    public function verify(EmailBackupVerifyRequest $request, ChallengeTokenService $challenges, MfaLoginService $login): UserResource
    {
        $token = $request->string('challenge_token')->toString();
        $user = $challenges->resolve($token, (string) $request->ip()) ?? throw MfaException::invalidChallenge();

        if (! $this->backup->verifyLoginChallenge($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => $this->backup->hasUsableCode($user, EmailOtpPurpose::Login)
                ? 'El código no es válido.'
                : 'El código venció o superaste los intentos. Pide uno nuevo.']);
        }

        if (! $challenges->consume($token)) {
            throw MfaException::invalidChallenge();
        }

        $login->startSession($request, $user, 'email_backup');

        return new UserResource($user->load('tenant'));
    }
}
