<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

// Referer simula una request del SPA propio: Sanctum's EnsureFrontendRequestsAreStateful
// solo activa sesión/cookies para requests cuyo Origin/Referer esté en sanctum.stateful.
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->withHeader('Referer', config('app.url')))
    ->in('Feature');

/** Dueño de la plataforma (crea el tenant AlertPrompt si aún no existe). */
function platformOwner(): User
{
    $platform = Tenant::platform() ?? Tenant::factory()->platform()->create();

    return User::factory()->for($platform)->create();
}

/** Código TOTP vigente del usuario ($offset en periodos de 30 s). */
function totp(User $user, int $offset = 0): string
{
    $google2fa = new Google2FA;

    return $google2fa->oathTotp((string) $user->two_factor_secret, $google2fa->getTimestamp() + $offset);
}

/** Primer paso del login: devuelve el challenge_token. */
function mfaChallengeToken(TestCase $test, User $user, string $password = 'password'): string
{
    return (string) $test->postJson('/api/login', ['email' => $user->email, 'password' => $password])
        ->assertOk()
        ->assertJsonPath('mfa_required', true)
        ->json('challenge_token');
}
