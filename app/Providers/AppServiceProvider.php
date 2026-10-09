<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant;
use App\Services\Authorization\PermissionRegistry;
use App\Services\CampaignPacer;
use App\Services\Channels\ChannelManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SendGrid;
use Twilio\Rest\Client as TwilioClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolvable por TenantScope y BelongsToTenant. Se sobreescribe con
        // app()->instance('current_tenant_id', $id) en el middleware de auth y
        // en los seeders. Nota: debe ser bind(), no instance(null) — Container::bound()
        // usa isset() internamente, que es false para un valor null cacheado en $instances.
        $this->app->bind('current_tenant_id', fn () => null);

        $this->app->singleton(ChannelManager::class);

        $this->app->singleton(PermissionRegistry::class, fn () => PermissionRegistry::fromConfig());

        // Bindeados por interfaz/clase para que los tests de drivers puedan
        // sustituirlos con mocks vía $this->instance() sin tocar credenciales reales.
        $this->app->singleton(TwilioClient::class, fn () => new TwilioClient(
            config('services.twilio.sid'),
            config('services.twilio.token'),
        ));

        $this->app->singleton(SendGrid::class, fn () => new SendGrid(config('services.sendgrid.key')));

        $this->app->singleton(
            CampaignPacer::class,
            fn () => new CampaignPacer((float) config('campaigns.failure_threshold')),
        );
    }

    public function boot(): void
    {
        // {client} en /api/admin/*: cualquier tenant salvo la propia plataforma.
        Route::bind('client', fn (string $value) => Tenant::query()
            ->where('is_platform', false)
            ->findOrFail((int) $value));

        // Detrás de un proxy TLS-terminating (túnel, load balancer) Laravel
        // ve la request como HTTP plano y genera URLs de assets con http://,
        // causando mixed-content en el navegador. Forzamos el scheme según
        // APP_URL en vez de confiar en lo que reporte la request entrante.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
