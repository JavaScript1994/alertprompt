<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Enums\AuthEventType;
use App\Enums\EmailOtpPurpose;
use App\Models\EmailBackupOtp;
use App\Models\User;
use App\Notifications\EmailBackupCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Correo de respaldo del segundo factor. No es un método elegible: solo se
 * configura (y verifica) durante el enrolamiento o desde Seguridad, y solo se
 * usa en el flujo "perdí mi dispositivo".
 *
 * Los códigos se guardan como HMAC-SHA256 con la APP_KEY y se comparan con
 * hash_equals() (tiempo constante). Bcrypt no sirve con hash_equals y para un
 * código de 6 dígitos que vence en 10 minutos con 5 intentos no aporta más.
 */
class EmailBackupService
{
    public function __construct(
        private readonly AuthEventRecorder $events,
        private readonly SecurityNotifier $notifier,
    ) {}

    /** Envía un código al correo nuevo. El respaldo vigente sigue hasta confirmar. */
    public function startSetup(User $user, string $email): void
    {
        $email = Str::lower(trim($email));

        $this->issue($user, EmailOtpPurpose::Setup, $email);
        $this->events->record(AuthEventType::EmailBackupRequested, $user, ['purpose' => 'setup']);
    }

    /** @return bool true si el código era válido y el correo quedó como respaldo */
    public function confirmSetup(User $user, string $code): bool
    {
        $otp = $this->check($user, EmailOtpPurpose::Setup, $code);
        if ($otp === null) {
            return false;
        }

        $changed = $user->hasVerifiedEmailBackup() && $user->two_factor_email_backup !== $otp->email;

        $user->forceFill([
            'two_factor_email_backup' => $otp->email,
            'two_factor_email_verified_at' => now(),
        ])->save();

        if ($changed) {
            $this->events->record(AuthEventType::EmailBackupChanged, $user);
            $this->notifier->backupMethodUsed($user, AuthEventType::EmailBackupChanged);
        }

        return true;
    }

    /** Login sin dispositivo: código al correo de respaldo verificado. */
    public function sendLoginChallenge(User $user): void
    {
        $this->issue($user, EmailOtpPurpose::Login, (string) $user->two_factor_email_backup);
        $this->events->record(AuthEventType::EmailBackupRequested, $user, ['purpose' => 'login']);
        $this->notifier->backupMethodUsed($user, AuthEventType::EmailBackupRequested);
    }

    public function verifyLoginChallenge(User $user, string $code): bool
    {
        if ($this->check($user, EmailOtpPurpose::Login, $code) === null) {
            $this->events->record(AuthEventType::MfaFailed, $user, ['method' => 'email_backup']);

            return false;
        }

        $this->events->record(AuthEventType::EmailBackupUsed, $user);
        $this->notifier->backupMethodUsed($user, AuthEventType::EmailBackupUsed);

        return true;
    }

    /** Código pendiente más reciente (para saber si hay que reiniciar el flujo). */
    public function hasUsableCode(User $user, EmailOtpPurpose $purpose): bool
    {
        return $this->latest($user, $purpose)?->isUsable() === true;
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function issue(User $user, EmailOtpPurpose $purpose, string $email): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $purpose, $email, $code) {
            // Un código nuevo invalida los anteriores del mismo propósito.
            $this->invalidateAll($user, $purpose);

            EmailBackupOtp::query()->create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'email' => $email,
                'code_hash' => $this->hash($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds((int) config('mfa.email_otp.ttl', 600)),
                'requested_at' => now(),
                'ip' => Request::ip(),
            ]);
        });

        Notification::route('mail', $email)->notify(new EmailBackupCodeNotification($code, $purpose, $user->name));
    }

    /**
     * Valida contra el último código del propósito. Cuenta el intento; al
     * llegar al máximo, el código queda invalidado y hay que pedir otro.
     * Al acertar, invalida TODOS los códigos pendientes del propósito.
     */
    private function check(User $user, EmailOtpPurpose $purpose, string $code): ?EmailBackupOtp
    {
        return DB::transaction(function () use ($user, $purpose, $code) {
            $otp = EmailBackupOtp::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null) {
                return null;
            }

            if (preg_match('/^\d{6}$/', $code) === 1 && hash_equals($otp->code_hash, $this->hash($code))) {
                $this->invalidateAll($user, $purpose);

                return $otp;
            }

            $otp->attempts++;
            if ($otp->attempts >= (int) config('mfa.email_otp.max_attempts', 5)) {
                $otp->used_at = now();
            }
            $otp->save();

            return null;
        });
    }

    private function invalidateAll(User $user, EmailOtpPurpose $purpose): void
    {
        EmailBackupOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function latest(User $user, EmailOtpPurpose $purpose): ?EmailBackupOtp
    {
        return EmailBackupOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();
    }
}
