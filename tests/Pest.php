<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

/**
 * PNG real para subir como foto (el contenedor no tiene GD, así que
 * UploadedFile::fake()->image() no sirve). $small: 40×40, bajo el mínimo.
 */
function fakePhoto(string $name = 'foto.png', bool $small = false): UploadedFile
{
    $png = $small
        ? 'iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAIAAAADnC86AAAAL0lEQVR42u3NMQ0AAAgDsClBIjfyMUHC06R/U9MvIhaLxWKxWCwWi8VisVgsFt9Z9wZZW6n4TIUAAAAASUVORK5CYII='
        : 'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4CAIAAAC2BqGFAAAAs0lEQVR42u3QMQ0AAAgDsClBIjfycQFPkypoapoDUSBaNKJFi7YgWjSiRYu2IFo0okWLRrRoRIsWjWjRiBYtGtGiES1aNKJFI1q0aESLRrRo0YgWjWjRohEtGtGiRSNaNKJFi0a0aESLFo1o0YgWLRrRohEtWjSiRSNatGhEi0a0aNGIFo1o0aIRLRrRokUjWjSiRYtGtGhEixaNaNGIFi0a0aIRLVo0okUjWrRoRItG9L8FeWokWMDMKNwAAAAASUVORK5CYII=';

    return UploadedFile::fake()->createWithContent($name, base64_decode($png));
}
