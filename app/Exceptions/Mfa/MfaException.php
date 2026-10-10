<?php

declare(strict_types=1);

namespace App\Exceptions\Mfa;

use App\Enums\SensitiveAction;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Respuesta de error del flujo de MFA con un `code` estable que el SPA usa
 * para decidir qué pantalla mostrar (enrolar, segundo paso, re-autenticar).
 */
class MfaException extends RuntimeException
{
    /** @param  array<string, mixed>  $extra */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 403,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function enrollmentRequired(): self
    {
        return new self('mfa_enrollment_required', 'Configura la verificación en dos pasos para continuar.');
    }

    public static function challengeRequired(): self
    {
        return new self('mfa_challenge_required', 'Ingresa el código de tu app de autenticación para continuar.');
    }

    public static function reauthRequired(SensitiveAction $action): self
    {
        return new self('reauth_required', 'Confirma tu contraseña y tu código para continuar.', 403, [
            'action' => $action->value,
            'action_label' => $action->label(),
        ]);
    }

    public static function invalidChallenge(): self
    {
        return new self('challenge_invalid', 'La sesión de verificación venció. Vuelve a iniciar sesión.', 401);
    }

    public static function emailBackupUnavailable(): self
    {
        return new self('email_backup_unavailable', 'No tienes un correo de respaldo verificado. Usa un código de recuperación.');
    }

    public static function tooManyAttempts(int $retryAfter): self
    {
        return new self('too_many_attempts', 'Demasiados intentos. Espera un momento antes de volver a intentar.', 429, [
            'retry_after' => $retryAfter,
        ]);
    }

    public function render(): JsonResponse
    {
        $headers = isset($this->extra['retry_after']) ? ['Retry-After' => (string) $this->extra['retry_after']] : [];

        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            ...$this->extra,
        ], $this->status, $headers);
    }
}
