<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Enums\AuthEventType;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (app de autenticación): enrolamiento, verificación de códigos, política
 * de quién debe enrolar y cuándo, y la marca de "segundo factor verificado"
 * de la sesión.
 */
class MfaService
{
    /** Clave de sesión: ['user_id' => int, 'at' => int]. */
    public const SESSION_KEY = 'mfa.verified';

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly RecoveryCodeService $recoveryCodes,
        private readonly AuthEventRecorder $events,
    ) {}

    /**
     * Inicia (o reinicia) el enrolamiento: secreto nuevo sin confirmar y
     * códigos de recuperación nuevos. El secreto y los códigos en claro solo
     * se devuelven aquí, una vez.
     *
     * @return array{secret: string, otpauth_url: string, qr_svg: string, recovery_codes: list<string>}
     */
    public function startEnrollment(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $codes = $this->recoveryCodes->generate($user);
        $url = $this->otpauthUrl($user, $secret);

        return [
            'secret' => $secret,
            'otpauth_url' => $url,
            'qr_svg' => $this->qrSvg($url),
            'recovery_codes' => $codes,
        ];
    }

    /** Confirma el enrolamiento con el primer código de la app. */
    public function confirmEnrollment(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null || $user->hasMfaEnabled()) {
            return false;
        }

        if (! $this->verifyUserCode($user, $code)) {
            $this->events->record(AuthEventType::MfaFailed, $user, ['stage' => 'enrollment']);

            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->events->record(AuthEventType::MfaEnrolled, $user);

        return true;
    }

    /** Borra todo el MFA del usuario (desactivar o reset por un administrador). */
    public function clear(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_email_backup' => null,
            'two_factor_email_verified_at' => null,
        ])->save();

        Cache::forget($this->lastStepKey($user));
    }

    /**
     * Verifica un código contra el secreto del usuario con ventana ±1 y sin
     * aceptar dos veces el mismo periodo (anti-replay).
     */
    public function verifyUserCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        $key = $this->lastStepKey($user);
        $step = $this->verifyCode($user->two_factor_secret, $code, Cache::get($key));

        if ($step === false) {
            return false;
        }

        // El periodo usado (y los anteriores) ya no sirven: ±1 cubre 90 s.
        Cache::put($key, $step, now()->addSeconds(120));

        return true;
    }

    /**
     * Núcleo puro de la verificación TOTP.
     *
     * @param  int|null  $lastStep  último periodo aceptado; no se acepta ese ni uno anterior
     * @param  int|null  $atStep  periodo "actual" (para tests); null = ahora
     * @return int|false el periodo que coincidió, o false
     */
    public function verifyCode(string $secret, string $code, ?int $lastStep = null, ?int $atStep = null): int|false
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $result = $this->google2fa->verifyKeyNewer(
            $secret,
            $code,
            $lastStep ?? 0,
            (int) config('mfa.totp_window', 1),
            $atStep,
        );

        return is_int($result) ? $result : false;
    }

    /**
     * Debe enrolar ya: tiene el permiso de gestionar usuarios (administradores
     * y el Administrador general) o venció su plazo de gracia.
     */
    public function mustEnrollNow(User $user): bool
    {
        if ($user->hasMfaEnabled()) {
            return false;
        }

        if ($this->requiresImmediateEnrollment($user)) {
            return true;
        }

        return $user->two_factor_grace_ends_at !== null && $user->two_factor_grace_ends_at->isPast();
    }

    public function requiresImmediateEnrollment(User $user): bool
    {
        // El usuario puede no ser del tenant activo (reset por un administrador
        // general): sus permisos se leen en su propio tenant y se restaura.
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($user->tenant_id);

        try {
            return $user->unsetRelation('roles')->unsetRelation('permissions')
                ->hasPermissionTo((string) config('mfa.immediate_permission'));
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    /** Fija el plazo de gracia en el primer login sin MFA (solo una vez). */
    public function startGracePeriod(User $user): ?Carbon
    {
        if ($user->hasMfaEnabled() || $this->requiresImmediateEnrollment($user)) {
            return null;
        }

        if ($user->two_factor_grace_ends_at === null) {
            $user->forceFill(['two_factor_grace_ends_at' => now()->addDays((int) config('mfa.grace_days', 14))])->save();
        }

        return $user->two_factor_grace_ends_at;
    }

    public function markSessionVerified(Session $session, User $user): void
    {
        $session->put(self::SESSION_KEY, ['user_id' => $user->id, 'at' => now()->getTimestamp()]);
    }

    public function isSessionVerified(Session $session, User $user): bool
    {
        $marker = $session->get(self::SESSION_KEY);

        return is_array($marker) && ($marker['user_id'] ?? null) === $user->id;
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl((string) config('mfa.issuer'), $user->email, $secret);
    }

    private function qrSvg(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    private function lastStepKey(User $user): string
    {
        return "mfa:totp-last-step:{$user->id}";
    }
}
