<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AuthEventType;
use App\Enums\SensitiveAction;
use App\Exceptions\Mfa\MfaException;
use App\Models\User;
use App\Services\Mfa\AuthEventRecorder;
use App\Services\Mfa\SensitiveActionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `reauth:{acción}`: la acción sensible exige contraseña + TOTP confirmados
 * hace menos de `mfa.reauth_ttl` (POST /api/reauth). Va después de `mfa`.
 */
class RequireReauth
{
    public function __construct(
        private readonly SensitiveActionService $actions,
        private readonly AuthEventRecorder $events,
    ) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        /** @var User $user */
        $user = $request->user();
        $sensitive = SensitiveAction::from($action);

        if (! $this->actions->isConfirmed($user, $sensitive)) {
            $this->events->record(AuthEventType::ReauthRequired, $user, ['action' => $sensitive->value]);

            throw MfaException::reauthRequired($sensitive);
        }

        return $next($request);
    }
}
