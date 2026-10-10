<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Exceptions\Mfa\MfaException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Límites de intentos del flujo de MFA (config/mfa.php → rate_limits). Cada
 * intento cuenta, acierte o no: el límite frena la fuerza bruta de códigos.
 */
class MfaThrottle
{
    /**
     * Cuenta un intento en cada clave; si alguna ya llegó a su máximo,
     * lanza 429 sin contar.
     *
     * @param  array<string, array{0: int, 1: int}>  $limits  clave => [máximo, segundos]
     */
    public function attempt(array $limits): void
    {
        foreach ($limits as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw MfaException::tooManyAttempts(RateLimiter::availableIn($key));
            }
        }

        foreach ($limits as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    /** @return array<string, array{0: int, 1: int}> */
    public function forVerify(int $userId, string $ip): array
    {
        return [
            "mfa:verify:user:{$userId}" => [$this->limit('verify_per_user', 5), 60],
            "mfa:verify:ip:{$ip}" => [$this->limit('verify_per_ip', 10), 60],
        ];
    }

    /** @return array<string, array{0: int, 1: int}> */
    public function forRecovery(int $userId): array
    {
        return ["mfa:recovery:user:{$userId}" => [$this->limit('recovery_per_user', 5), 60]];
    }

    /** @return array<string, array{0: int, 1: int}> */
    public function forConfirm(int $userId): array
    {
        return ["mfa:confirm:user:{$userId}" => [$this->limit('confirm_per_user', 5), 60]];
    }

    /** @return array<string, array{0: int, 1: int}> */
    public function forEmailChallenge(int $userId): array
    {
        return ["mfa:email-challenge:user:{$userId}" => [$this->limit('email_challenge_per_user', 3), 900]];
    }

    /** @return array<string, array{0: int, 1: int}> */
    public function forEmailSetup(int $userId): array
    {
        return ["mfa:email-setup:user:{$userId}" => [$this->limit('email_setup_per_user', 3), 900]];
    }

    /** @return array<string, array{0: int, 1: int}> */
    public function forReauth(int $userId): array
    {
        return ["mfa:reauth:user:{$userId}" => [5, 60]];
    }

    private function limit(string $name, int $default): int
    {
        return (int) config("mfa.rate_limits.{$name}", $default);
    }
}
