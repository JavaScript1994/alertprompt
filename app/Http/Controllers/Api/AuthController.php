<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AuthEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Mfa\AuthEventRecorder;
use App\Services\Mfa\ChallengeTokenService;
use App\Services\Mfa\MfaLoginService;
use App\Support\TenantDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Primer paso. Con MFA enrolado no abre sesión: devuelve un
     * challenge_token para POST /api/mfa/verify. Sin MFA abre la sesión y el
     * middleware `mfa` decide si debe enrolar ya o tiene plazo de gracia.
     */
    public function login(
        LoginRequest $request,
        MfaLoginService $login,
        ChallengeTokenService $challenges,
        AuthEventRecorder $events,
    ): UserResource|JsonResponse {
        $user = $login->checkPassword(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            TenantDomain::current($request),
            (bool) $request->attributes->get('domain_unknown', false),
        );

        if ($user->hasMfaEnabled()) {
            $events->record(AuthEventType::MfaChallenge, $user);

            return response()->json([
                'mfa_required' => true,
                'challenge_token' => $challenges->issue($user, (string) $request->ip()),
                'expires_in' => (int) config('mfa.challenge_ttl'),
                'email_backup_available' => $user->hasVerifiedEmailBackup(),
            ]);
        }

        $login->startSession($request, $user);

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
