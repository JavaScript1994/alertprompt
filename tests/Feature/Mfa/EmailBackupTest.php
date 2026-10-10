<?php

declare(strict_types=1);

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\EmailBackupOtp;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BackupMethodUsedNotification;
use App\Notifications\EmailBackupCodeNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($tenant)->create();
    $this->user = User::factory()->withRole('client-user')->for($tenant)->create();
});

/** Último código enviado por correo (solo existe en claro en la notificación). */
function lastEmailCode(): string
{
    $code = '';
    Notification::assertSentOnDemand(EmailBackupCodeNotification::class, function ($notification) use (&$code) {
        $code = (fn () => $this->code)->call($notification);

        return true;
    });

    return $code;
}

function withVerifiedBackup(User $user): User
{
    $user->forceFill(['two_factor_email_backup' => 'respaldo@example.com', 'two_factor_email_verified_at' => now()])->save();

    return $user;
}

it('sets up the backup email during enrollment with a code', function () {
    $user = User::factory()->withoutMfa()->create();

    $this->actingAsWithoutMfa($user)->postJson('/api/mfa/email-backup/setup', ['email' => 'Respaldo@Example.com'])->assertStatus(202);
    $this->postJson('/api/mfa/email-backup/confirm', ['code' => lastEmailCode()])
        ->assertOk()
        ->assertJsonPath('data.email_backup', 'r*******@example.com');

    expect($user->fresh()->two_factor_email_backup)->toBe('respaldo@example.com')
        ->and($user->fresh()->two_factor_email_verified_at)->not->toBeNull()
        ->and(AuthEvent::query()->where('event', AuthEventType::EmailBackupRequested)->exists())->toBeTrue();
});

it('rejects the login email as backup', function () {
    $user = User::factory()->withoutMfa()->create(['email' => 'yo@example.com']);

    $this->actingAsWithoutMfa($user)->postJson('/api/mfa/email-backup/setup', ['email' => 'YO@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('refuses the email challenge with 403 when there is no verified backup email', function () {
    $this->postJson('/api/mfa/email-backup/challenge', ['challenge_token' => mfaChallengeToken($this, $this->user)])
        ->assertForbidden()
        ->assertJsonPath('code', 'email_backup_unavailable');

    Notification::assertNothingSentTo(new AnonymousNotifiable);
});

it('rate limits the email challenge to 3 per 15 minutes', function () {
    withVerifiedBackup($this->user);
    $token = mfaChallengeToken($this, $this->user);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/mfa/email-backup/challenge', ['challenge_token' => $token])->assertStatus(202);
    }

    $this->postJson('/api/mfa/email-backup/challenge', ['challenge_token' => $token])->assertStatus(429);
});

it('logs in with the backup email code, records the event and notifies the administrators', function () {
    withVerifiedBackup($this->user);
    $token = mfaChallengeToken($this, $this->user);

    $this->postJson('/api/mfa/email-backup/challenge', ['challenge_token' => $token])
        ->assertStatus(202)
        ->assertJsonPath('email', 'r*******@example.com');

    Notification::assertSentTo($this->admin, BackupMethodUsedNotification::class, fn ($n) => $n->event === AuthEventType::EmailBackupRequested);

    $this->postJson('/api/mfa/email-backup/verify', ['challenge_token' => $token, 'code' => lastEmailCode()])->assertOk();

    $this->assertAuthenticatedAs($this->user, 'web');
    expect(AuthEvent::query()->where('event', AuthEventType::EmailBackupUsed)->exists())->toBeTrue()
        ->and(EmailBackupOtp::query()->whereNull('used_at')->count())->toBe(0);
    Notification::assertSentTo($this->admin, BackupMethodUsedNotification::class, fn ($n) => $n->event === AuthEventType::EmailBackupUsed);
});

it('invalidates the email code after 5 failed attempts and asks to start over', function () {
    withVerifiedBackup($this->user);
    $token = mfaChallengeToken($this, $this->user);
    $this->postJson('/api/mfa/email-backup/challenge', ['challenge_token' => $token])->assertStatus(202);
    $code = lastEmailCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/mfa/email-backup/verify', ['challenge_token' => $token, 'code' => $wrong])->assertUnprocessable();
    }

    $this->postJson('/api/mfa/email-backup/verify', ['challenge_token' => $token, 'code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'El código venció o superaste los intentos. Pide uno nuevo.');

    $this->assertGuest('web');
});

it('requires re-auth to replace a verified backup email and notifies the change', function () {
    withVerifiedBackup($this->user);

    $this->actingAs($this->user)->postJson('/api/mfa/email-backup/setup', ['email' => 'nuevo@example.com'])
        ->assertForbidden()
        ->assertJsonPath('action', 'change_email_backup');

    $this->postJson('/api/reauth', ['action' => 'change_email_backup', 'password' => 'password', 'code' => totp($this->user)])->assertOk();
    $this->postJson('/api/mfa/email-backup/setup', ['email' => 'nuevo@example.com'])->assertStatus(202);
    $this->postJson('/api/mfa/email-backup/confirm', ['code' => lastEmailCode()])->assertOk();

    expect($this->user->fresh()->two_factor_email_backup)->toBe('nuevo@example.com');
    Notification::assertSentTo($this->admin, BackupMethodUsedNotification::class, fn ($n) => $n->event === AuthEventType::EmailBackupChanged);
});
