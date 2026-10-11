<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Identificador de subdominio de un cliente: a-z, 0-9 y guiones, 3 a 40 caracteres. */
final class TenantSlug
{
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

    /** Genera uno libre a partir del nombre ("Andina SAC" → andina-sac, andina-sac-2...). */
    public static function unique(string $name, ?int $ignoreTenantId = null): string
    {
        $base = Str::of(Str::slug($name))->limit(36, '')->trim('-')->toString();
        if (strlen($base) < 3 || self::isReserved($base)) {
            $base = 'empresa-'.($base !== '' ? $base : Str::lower(Str::random(6)));
            $base = Str::of($base)->limit(36, '')->trim('-')->toString();
        }

        $slug = $base;
        for ($n = 2; self::taken($slug, $ignoreTenantId); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, (array) config('branding.reserved_slugs', []), true);
    }

    private static function taken(string $slug, ?int $ignoreTenantId): bool
    {
        return DB::table('tenants')
            ->where('slug', $slug)
            ->when($ignoreTenantId, fn ($query) => $query->where('id', '!=', $ignoreTenantId))
            ->exists();
    }
}
