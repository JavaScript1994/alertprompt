<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Códigos de recuperación de un solo uso. En la base solo queda la lista de
 * hashes (Hash::make), además cifrada con la APP_KEY.
 */
class RecoveryCodeService
{
    /**
     * Reemplaza los códigos del usuario. Devuelve los códigos en claro: es la
     * única vez que existen fuera del usuario.
     *
     * @return list<string>
     */
    public function generate(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < (int) config('mfa.recovery_codes.count', 8); $i++) {
            $codes[] = $this->makeCode();
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $codes),
        ])->save();

        return $codes;
    }

    /** Valida y consume un código (deja de servir aunque se reintente). */
    public function consume(User $user, string $code): bool
    {
        $normalized = $this->normalize($code);
        if ($normalized === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $normalized) {
            /** @var User $locked */
            $locked = User::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($user->id);
            $hashes = $locked->two_factor_recovery_codes ?? [];

            foreach ($hashes as $index => $hash) {
                if (Hash::check($normalized, $hash)) {
                    unset($hashes[$index]);
                    $locked->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                    $user->setRawAttributes($locked->getAttributes(), true);

                    return true;
                }
            }

            return false;
        });
    }

    public function remaining(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function shouldRegenerate(User $user): bool
    {
        return $this->remaining($user) <= (int) config('mfa.recovery_codes.regenerate_threshold', 2);
    }

    /** Formato XXXXX-XXXXX, sin caracteres ambiguos. */
    private function makeCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $raw = '';
        for ($i = 0; $i < 10; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($raw, 0, 5).'-'.substr($raw, 5);
    }

    /** Acepta minúsculas, espacios y el guion opcional. */
    private function normalize(string $code): string
    {
        $clean = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return strlen($clean) === 10 ? substr($clean, 0, 5).'-'.substr($clean, 5) : '';
    }
}
