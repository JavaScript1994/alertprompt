<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Mfa\MfaLoginService;
use App\Services\Mfa\MfaService;
use App\Services\Mfa\RecoveryCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estado del segundo factor del usuario autenticado. Nunca incluye el
 * secreto ni los códigos; el correo de respaldo va enmascarado.
 *
 * @mixin User
 */
class MfaStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mfa = app(MfaService::class);
        $codes = app(RecoveryCodeService::class);
        $enabled = $this->resource->hasMfaEnabled();

        return [
            'enabled' => $enabled,
            'confirmed_at' => $this->two_factor_confirmed_at,
            'required_now' => $mfa->mustEnrollNow($this->resource),
            'grace_ends_at' => $enabled ? null : $this->two_factor_grace_ends_at,
            'session_verified' => $enabled && $request->hasSession() && $mfa->isSessionVerified($request->session(), $this->resource),
            'can_reenroll' => $request->hasSession() && app(MfaLoginService::class)->inReenrollWindow($request),
            'email_backup' => $this->resource->hasVerifiedEmailBackup() ? self::mask((string) $this->two_factor_email_backup) : null,
            'recovery_codes_remaining' => $enabled ? $codes->remaining($this->resource) : 0,
            'should_regenerate_codes' => $enabled && $codes->shouldRegenerate($this->resource),
        ];
    }

    /** ana.perez@dominio.com → a*******@dominio.com */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('*', max(1, mb_strlen($local) - 1)).'@'.$domain;
    }
}
