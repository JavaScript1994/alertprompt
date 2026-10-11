<?php

declare(strict_types=1);

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BackupMethodUsedNotification;
use App\Services\Mfa\RecoveryCodeService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($tenant)->create();
    $this->user = User::factory()->withRole('client-user')->for($tenant)->create();
    $this->codes = app(RecoveryCodeService::class)->generate($this->user);
});

it('logs in with a recovery code once; the same code fails the second time', function () {
    $this->postJson('/api/mfa/recovery', ['challenge_token' => mfaChallengeToken($this, $this->user), 'recovery_code' => $this->codes[0]])
        ->assertOk()
        ->assertJsonPath('data.mfa.recovery_codes_remaining', 7)
        ->assertJsonPath('data.mfa.can_reenroll', true);

    $this->assertAuthenticatedAs($this->user, 'web');
    expect(AuthEvent::query()->where('event', AuthEventType::RecoveryCodeUsed)->exists())->toBeTrue();

    $this->postJson('/api/logout');

    $this->postJson('/api/mfa/recovery', ['challenge_token' => mfaChallengeToken($this, $this->user), 'recovery_code' => $this->codes[0]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recovery_code');
});

it('records mfa_failed for an invalid recovery code', function () {
    $this->postJson('/api/mfa/recovery', ['challenge_token' => mfaChallengeToken($this, $this->user), 'recovery_code' => 'AAAAA-AAAAA'])
        ->assertUnprocessable();

    $this->assertGuest('web');
    expect(AuthEvent::query()->where('event', AuthEventType::MfaFailed)->where('user_id', $this->user->id)->exists())->toBeTrue();
});

it('notifies the account administrators and the user when a recovery code is used', function () {
    $this->postJson('/api/mfa/recovery', ['challenge_token' => mfaChallengeToken($this, $this->user), 'recovery_code' => $this->codes[1]])->assertOk();

    Notification::assertSentTo($this->admin, BackupMethodUsedNotification::class, fn ($n) => $n->event === AuthEventType::RecoveryCodeUsed);
    Notification::assertSentTo($this->user, BackupMethodUsedNotification::class);
});

it('lets the user set up a new authenticator right after entering with a recovery code, without re-auth', function () {
    $this->postJson('/api/mfa/recovery', ['challenge_token' => mfaChallengeToken($this, $this->user), 'recovery_code' => $this->codes[2]])->assertOk();

    $this->postJson('/api/mfa/setup')->assertOk();
});

it('asks to regenerate when two or fewer codes are left', function () {
    foreach (array_slice($this->codes, 0, 6) as $code) {
        app(RecoveryCodeService::class)->consume($this->user, $code);
    }

    $this->postJson('/api/mfa/recovery', ['challenge_token' => mfaChallengeToken($this, $this->user), 'recovery_code' => $this->codes[6]])
        ->assertOk()
        ->assertJsonPath('data.mfa.recovery_codes_remaining', 1)
        ->assertJsonPath('data.mfa.should_regenerate_codes', true);
});

it('rate limits recovery attempts to 5 per minute', function () {
    $token = mfaChallengeToken($this, $this->user);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/mfa/recovery', ['challenge_token' => $token, 'recovery_code' => 'AAAAA-AAAAA'])->assertUnprocessable();
    }

    $this->postJson('/api/mfa/recovery', ['challenge_token' => $token, 'recovery_code' => $this->codes[0]])->assertStatus(429);
});
