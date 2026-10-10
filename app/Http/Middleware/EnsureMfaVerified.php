<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Mfa\MfaException;
use App\Models\User;
use App\Services\Mfa\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El segundo factor se exige SIEMPRE aquí, nunca solo en el frontend.
 *
 * - `mfa` (rutas normales):
 *     1. Sin MFA y obligado a enrolar (administra usuarios o venció su
 *        plazo de gracia) → 403 mfa_enrollment_required.
 *     2. Sin MFA dentro del plazo de gracia → pasa.
 *     3. Con MFA y la sesión sin segundo factor → 403 mfa_challenge_required.
 * - `mfa:enrollment` (configurar el MFA): pasa sin MFA (para poder enrolar);
 *   con MFA exige la sesión verificada.
 *
 * Las acciones sensibles se protegen aparte con `reauth:{acción}`.
 */
class EnsureMfaVerified
{
    public function __construct(private readonly MfaService $mfa) {}

    public function handle(Request $request, Closure $next, string $mode = 'full'): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->hasMfaEnabled()) {
            if ($mode !== 'enrollment' && $this->mfa->mustEnrollNow($user)) {
                throw MfaException::enrollmentRequired();
            }

            return $next($request);
        }

        if (! $request->hasSession() || ! $this->mfa->isSessionVerified($request->session(), $user)) {
            throw MfaException::challengeRequired();
        }

        return $next($request);
    }
}
