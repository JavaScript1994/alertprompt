<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Token del segundo paso del login. Cifrado y firmado con la APP_KEY
 * (Crypt, AES-256 + HMAC): cumple el papel de un JWT corto sin agregar una
 * librería. Claims: user_id, IP, nonce y expiración. Un solo uso: al
 * completar el login el nonce se marca usado en la caché (Redis) hasta que
 * el token expira.
 */
class ChallengeTokenService
{
    public function issue(User $user, string $ip): string
    {
        return Crypt::encryptString(json_encode([
            'purpose' => 'mfa_challenge',
            'uid' => $user->id,
            'ip' => $ip,
            'nonce' => Str::random(40),
            'exp' => now()->addSeconds((int) config('mfa.challenge_ttl', 300))->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Valida el token sin consumirlo (un código equivocado permite reintentar
     * con el mismo token). Null si es inválido, expiró, cambió la IP o ya se usó.
     */
    public function resolve(string $token, string $ip): ?User
    {
        $claims = $this->claims($token);

        if ($claims === null || $claims['ip'] !== $ip || Cache::has($this->usedKey($claims['nonce']))) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->withoutGlobalScopes()->find($claims['uid']);

        return $user !== null && $user->isActive() ? $user : null;
    }

    /** Marca el token como usado. False si otro request se adelantó. */
    public function consume(string $token): bool
    {
        $claims = $this->claims($token);
        if ($claims === null) {
            return false;
        }

        $ttl = max(1, $claims['exp'] - now()->getTimestamp());

        return Cache::add($this->usedKey($claims['nonce']), true, $ttl);
    }

    /** @return array{uid: int, ip: string, nonce: string, exp: int}|null */
    private function claims(string $token): ?array
    {
        try {
            $claims = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($claims)
            || ($claims['purpose'] ?? null) !== 'mfa_challenge'
            || ! is_int($claims['uid'] ?? null)
            || ! is_string($claims['ip'] ?? null)
            || ! is_string($claims['nonce'] ?? null)
            || ! is_int($claims['exp'] ?? null)
            || $claims['exp'] < now()->getTimestamp()) {
            return null;
        }

        return $claims;
    }

    private function usedKey(string $nonce): string
    {
        return 'mfa:challenge-used:'.hash('sha256', $nonce);
    }
}
