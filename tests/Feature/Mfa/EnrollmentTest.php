<?php

declare(strict_types=1);

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

beforeEach(fn () => Notification::fake());

/** Código vigente para el secreto pendiente devuelto por /mfa/setup. */
function pendingCode(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

it('enrolls: setup returns a QR and the secret once, confirm activates it and returns 8 recovery codes', function () {
    $user = User::factory()->withoutMfa()->create();

    $setup = $this->actingAsWithoutMfa($user)->postJson('/api/mfa/setup')->assertOk();
    $secret = $setup->json('data.secret');

    expect($setup->json('data.qr_svg'))->toContain('<svg')
        ->and($setup->json('data.otpauth_url'))->toStartWith('otpauth://totp/')
        ->and($user->fresh()->hasMfaEnabled())->toBeFalse();

    // El secreto nunca queda en claro en la base.
    expect((string) DB::table('users')->where('id', $user->id)->value('two_factor_pending_secret'))->not->toContain($secret);

    $confirm = $this->postJson('/api/mfa/confirm', ['code' => pendingCode($secret)])->assertOk();

    expect($confirm->json('data.recovery_codes'))->toHaveCount(8)
        ->and($user->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->fresh()->two_factor_pending_secret)->toBeNull()
        ->and($confirm->json('data.user.mfa.session_verified'))->toBeTrue()
        ->and(AuthEvent::query()->where('event', AuthEventType::MfaEnrolled)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('does not enroll with an invalid code', function () {
    $user = User::factory()->withoutMfa()->create();
    $this->actingAsWithoutMfa($user)->postJson('/api/mfa/setup')->assertOk();

    $this->postJson('/api/mfa/confirm', ['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');

    expect($user->fresh()->hasMfaEnabled())->toBeFalse()
        ->and(AuthEvent::query()->where('event', AuthEventType::MfaFailed)->exists())->toBeTrue();
});

it('rate limits enrollment confirmations to 5 per minute', function () {
    $user = User::factory()->withoutMfa()->create();
    $this->actingAsWithoutMfa($user)->postJson('/api/mfa/setup')->assertOk();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/mfa/confirm', ['code' => '000000'])->assertUnprocessable();
    }

    $this->postJson('/api/mfa/confirm', ['code' => '000000'])->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
});

it('blocks an administrator without MFA with mfa_enrollment_required right after login', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->withoutMfa()->for($tenant)->create(['email' => 'admin@cliente.pe']);

    $login = $this->postJson('/api/login', ['email' => 'admin@cliente.pe', 'password' => 'password'])->assertOk();
    expect($login->json('data.mfa.required_now'))->toBeTrue();

    $this->getJson('/api/contacts')->assertForbidden()->assertJsonPath('code', 'mfa_enrollment_required');
    $this->getJson('/api/users')->assertForbidden()->assertJsonPath('code', 'mfa_enrollment_required');

    // Lo único que puede hacer: enrolar (y ver quién es / salir).
    $this->getJson('/api/user')->assertOk();
    $this->postJson('/api/mfa/setup')->assertOk();
    expect($admin->fresh()->two_factor_grace_ends_at)->toBeNull();
});

it('blocks the platform owner without MFA too', function () {
    $owner = platformOwner();
    $owner->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

    $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'password'])->assertOk();

    $this->getJson('/api/admin/clients')->assertForbidden()->assertJsonPath('code', 'mfa_enrollment_required');
});

it('gives a user without users.manage 14 days of grace and then forces enrollment', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->withoutMfa()->withRole('client-user')->for($tenant)->create(['email' => 'operador@cliente.pe']);

    $login = $this->postJson('/api/login', ['email' => 'operador@cliente.pe', 'password' => 'password'])->assertOk();
    expect($login->json('data.mfa.required_now'))->toBeFalse()
        ->and($login->json('data.mfa.grace_ends_at'))->not->toBeNull();

    $this->getJson('/api/contacts')->assertOk();

    $this->travel(13)->days();
    $this->travel(23)->hours();
    $this->getJson('/api/contacts')->assertOk();

    $this->travel(2)->hours();
    $this->getJson('/api/contacts')->assertForbidden()->assertJsonPath('code', 'mfa_enrollment_required');
});

it('does not restart the grace period on later logins', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutMfa()->withRole('client-user')->for($tenant)->create(['email' => 'operador@cliente.pe']);

    $this->postJson('/api/login', ['email' => 'operador@cliente.pe', 'password' => 'password'])->assertOk();
    $first = $user->fresh()->two_factor_grace_ends_at;

    $this->travel(5)->days();
    $this->postJson('/api/logout');
    $this->postJson('/api/login', ['email' => 'operador@cliente.pe', 'password' => 'password'])->assertOk();

    expect($user->fresh()->two_factor_grace_ends_at->equalTo($first))->toBeTrue();
});

it('keeps the current authenticator until a new one is confirmed when changing device', function () {
    $user = User::factory()->create();
    $oldSecret = $user->two_factor_secret;

    // Con MFA activo, cambiar de dispositivo exige re-autenticación.
    $this->actingAs($user)->postJson('/api/mfa/setup')->assertForbidden()
        ->assertJsonPath('code', 'reauth_required')
        ->assertJsonPath('action', 'change_authenticator');

    $this->postJson('/api/reauth', ['action' => 'change_authenticator', 'password' => 'password', 'code' => totp($user)])->assertOk();
    $this->postJson('/api/mfa/setup')->assertOk();

    expect($user->fresh()->two_factor_secret)->toBe($oldSecret)
        ->and($user->fresh()->hasMfaEnabled())->toBeTrue();
});
