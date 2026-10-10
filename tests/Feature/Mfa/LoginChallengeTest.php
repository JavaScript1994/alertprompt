<?php

declare(strict_types=1);

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->for(Tenant::factory())->create(['email' => 'admin@cliente.pe']);
});

it('does not open a session with the password when MFA is enrolled: returns a challenge token', function () {
    $response = $this->postJson('/api/login', ['email' => 'admin@cliente.pe', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('mfa_required', true)
        ->assertJsonPath('email_backup_available', false);

    expect($response->json('challenge_token'))->toBeString()->not->toBeEmpty();
    $this->assertGuest('web');
    expect(AuthEvent::query()->where('event', AuthEventType::MfaChallenge)->exists())->toBeTrue();
});

it('opens the session with a valid TOTP and records mfa_success and the last login', function () {
    $token = mfaChallengeToken($this, $this->user);

    $this->postJson('/api/mfa/verify', ['challenge_token' => $token, 'code' => totp($this->user)])
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@cliente.pe')
        ->assertJsonPath('data.mfa.session_verified', true);

    $this->assertAuthenticatedAs($this->user, 'web');
    $this->getJson('/api/contacts')->assertOk();

    expect(AuthEvent::query()->where('event', AuthEventType::MfaSuccess)->where('user_id', $this->user->id)->exists())->toBeTrue()
        ->and($this->user->fresh()->last_login_at)->not->toBeNull()
        ->and($this->user->fresh()->last_login_ip)->toBe('127.0.0.1');
});

it('rejects an invalid TOTP, records mfa_failed and rate limits after 5 attempts', function () {
    $token = mfaChallengeToken($this, $this->user);
    $wrong = totp($this->user) === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/mfa/verify', ['challenge_token' => $token, 'code' => $wrong])->assertUnprocessable();
    }

    // Ni el código correcto pasa mientras dure el bloqueo.
    $this->postJson('/api/mfa/verify', ['challenge_token' => $token, 'code' => totp($this->user)])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_attempts');

    $this->assertGuest('web');
    expect(AuthEvent::query()->where('event', AuthEventType::MfaFailed)->count())->toBe(5);
});

it('rejects an expired challenge token with 401', function () {
    $token = mfaChallengeToken($this, $this->user);

    $this->travel(301)->seconds();

    $this->postJson('/api/mfa/verify', ['challenge_token' => $token, 'code' => totp($this->user)])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'challenge_invalid');
});

it('rejects a reused challenge token with 401', function () {
    $token = mfaChallengeToken($this, $this->user);

    $this->postJson('/api/mfa/verify', ['challenge_token' => $token, 'code' => totp($this->user)])->assertOk();
    $this->postJson('/api/logout');

    $this->postJson('/api/mfa/verify', ['challenge_token' => $token, 'code' => totp($this->user, 1)])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'challenge_invalid');
});

it('rejects a challenge token used from another IP', function () {
    $token = mfaChallengeToken($this, $this->user);

    $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])
        ->postJson('/api/mfa/verify', ['challenge_token' => $token, 'code' => totp($this->user)])
        ->assertUnauthorized();
});

it('rejects a tampered challenge token', function () {
    $this->postJson('/api/mfa/verify', ['challenge_token' => 'eyJpdiI6ImZha2UifQ==', 'code' => '123456'])
        ->assertUnauthorized();
});

it('does not accept the same TOTP twice', function () {
    $code = totp($this->user);

    $this->postJson('/api/mfa/verify', ['challenge_token' => mfaChallengeToken($this, $this->user), 'code' => $code])->assertOk();
    $this->postJson('/api/logout');

    $this->postJson('/api/mfa/verify', ['challenge_token' => mfaChallengeToken($this, $this->user), 'code' => $code])
        ->assertUnprocessable();
});

it('requires the second factor on a session that only passed the password', function () {
    $this->actingAsWithoutMfa($this->user)->getJson('/api/contacts')
        ->assertForbidden()
        ->assertJsonPath('code', 'mfa_challenge_required');
});

it('never exposes the secret or recovery codes in the user payload', function () {
    $this->actingAs($this->user)->getJson('/api/user')
        ->assertOk()
        ->assertJsonMissingPath('data.two_factor_secret')
        ->assertJsonMissingPath('data.two_factor_recovery_codes')
        ->assertJsonPath('data.mfa.enabled', true);
});
