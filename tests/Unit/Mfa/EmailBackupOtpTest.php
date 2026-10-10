<?php

declare(strict_types=1);

use App\Enums\EmailOtpPurpose;
use App\Models\EmailBackupOtp;
use App\Models\User;
use App\Notifications\EmailBackupCodeNotification;
use App\Services\Mfa\EmailBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->service = app(EmailBackupService::class);
    $this->user = User::factory()->create();
});

/** Lee el código en claro de la notificación enviada (solo en tests). */
function sentEmailCode(): string
{
    $code = null;
    Notification::assertSentOnDemand(EmailBackupCodeNotification::class, function ($notification) use (&$code) {
        $code = (fn () => $this->code)->call($notification);

        return true;
    });

    return (string) $code;
}

it('stores only the HMAC of the code', function () {
    $this->service->startSetup($this->user, 'Respaldo@Example.com');
    $code = sentEmailCode();

    $otp = EmailBackupOtp::query()->sole();
    expect($otp->code_hash)->not->toContain($code)
        ->and($otp->code_hash)->toBe(hash_hmac('sha256', $code, (string) config('app.key')))
        ->and($otp->email)->toBe('respaldo@example.com');
});

it('confirms with the right code only once', function () {
    $this->service->startSetup($this->user, 'respaldo@example.com');
    $code = sentEmailCode();

    expect($this->service->confirmSetup($this->user, $code))->toBeTrue()
        ->and($this->user->fresh()->hasVerifiedEmailBackup())->toBeTrue()
        ->and($this->service->confirmSetup($this->user, $code))->toBeFalse();
});

it('rejects an expired code', function () {
    $this->service->startSetup($this->user, 'respaldo@example.com');
    $code = sentEmailCode();

    $this->travel(11)->minutes();

    expect($this->service->confirmSetup($this->user, $code))->toBeFalse();
});

it('invalidates the code after five wrong attempts', function () {
    $this->service->startSetup($this->user, 'respaldo@example.com');
    $code = sentEmailCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        expect($this->service->confirmSetup($this->user, $wrong))->toBeFalse();
    }

    expect($this->service->confirmSetup($this->user, $code))->toBeFalse()
        ->and($this->service->hasUsableCode($this->user, EmailOtpPurpose::Setup))->toBeFalse();
});

it('keeps the current backup email until the new one is confirmed', function () {
    $this->user->forceFill(['two_factor_email_backup' => 'viejo@example.com', 'two_factor_email_verified_at' => now()])->save();

    $this->service->startSetup($this->user, 'nuevo@example.com');

    expect($this->user->fresh()->two_factor_email_backup)->toBe('viejo@example.com');
    Notification::assertSentOnDemand(
        EmailBackupCodeNotification::class,
        fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'nuevo@example.com',
    );
});
