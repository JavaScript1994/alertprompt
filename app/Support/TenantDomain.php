<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Modules\TenantModules;
use Illuminate\Http\Request;

/**
 * Resuelve el cliente por el subdominio de la petición y arma su URL.
 * {slug}.{base_domain} → ese cliente; el dominio base → login general.
 */
final class TenantDomain
{
    public const REQUEST_ATTRIBUTE = 'domain_tenant';

    /**
     * @return array{tenant: ?Tenant, unknown: bool} unknown = subdominio que no es de ningún cliente
     */
    public static function resolve(Request $request): array
    {
        $host = strtolower($request->getHost());
        $base = strtolower((string) config('branding.base_domain'));

        if ($host === $base || ! str_ends_with($host, '.'.$base)) {
            return ['tenant' => null, 'unknown' => false];
        }

        $slug = substr($host, 0, -strlen('.'.$base));
        if (str_contains($slug, '.') || preg_match(TenantSlug::PATTERN, $slug) !== 1) {
            return ['tenant' => null, 'unknown' => true];
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->withoutGlobalScope(TenantScope::class)
            ->where('slug', $slug)
            ->where('is_platform', false)
            ->first();

        return ['tenant' => $tenant, 'unknown' => $tenant === null];
    }

    /** Cliente del subdominio de esta petición (lo fija ResolveTenantDomain). */
    public static function current(Request $request): ?Tenant
    {
        return $request->attributes->get(self::REQUEST_ATTRIBUTE);
    }

    /** https://{slug}.{base_domain}[:puerto], con el esquema y puerto de APP_URL. */
    public static function urlFor(Tenant $tenant): ?string
    {
        if ($tenant->slug === null) {
            return null;
        }

        $app = parse_url((string) config('app.url'));
        $scheme = $app['scheme'] ?? 'https';
        $port = isset($app['port']) ? ':'.$app['port'] : '';

        return "{$scheme}://{$tenant->slug}.".config('branding.base_domain').$port;
    }

    /**
     * URL base para los enlaces de correo de un usuario: su subdominio si su
     * empresa tiene el login con marca, si no el dominio general.
     */
    public static function linkBaseFor(Tenant $tenant): string
    {
        $branded = ! $tenant->is_platform
            && app(TenantModules::class)->isEnabled((string) config('branding.module'), $tenant);

        return rtrim(($branded ? self::urlFor($tenant) : null) ?? (string) config('app.url'), '/');
    }
}
