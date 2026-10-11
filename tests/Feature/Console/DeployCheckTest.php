<?php

declare(strict_types=1);

use App\Services\Deployment\DeploymentChecks;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

/** Configuración de producción correcta (sin Redis: cola en base de datos). */
function productionReady(): void
{
    config([
        'app.debug' => false,
        'app.url' => 'https://alertprompt.pe',
        'branding.base_domain' => 'alertprompt.pe',
        'session.domain' => null,
        'session.secure' => true,
        'queue.default' => 'database',
        'cache.default' => 'array',
        'mail.default' => 'smtp',
    ]);
    Cache::put(DeploymentChecks::HEARTBEAT_KEY, now()->toIso8601String());
}

/** @return array<string, array{check: string, status: string, message: string}> */
function deployResults(bool $production = true): array
{
    return collect(app(DeploymentChecks::class)->run($production))->keyBy('check')->all();
}

it('passes a correctly configured production deployment', function () {
    productionReady();

    $this->artisan('deploy:check --production')->assertSuccessful()->expectsOutputToContain('Todo listo.');
    expect(collect(deployResults())->pluck('status')->unique()->all())->toBe(['ok']);
});

it('fails on unsafe or incomplete production settings', function () {
    productionReady();
    config([
        'app.debug' => true,
        'app.url' => 'http://alertprompt.pe',
        'branding.base_domain' => 'localhost',
        'session.domain' => '.alertprompt.pe',
        'queue.default' => 'sync',
        'mail.default' => 'log',
    ]);

    $results = deployResults();

    foreach (['APP_DEBUG', 'APP_URL', 'APP_BASE_DOMAIN', 'SESSION_DOMAIN', 'QUEUE_CONNECTION', 'MAIL_MAILER'] as $check) {
        expect($results[$check]['status'])->toBe('fail', $check);
    }
    $this->artisan('deploy:check --production')->assertFailed();
});

it('requires a secure session cookie behind https', function () {
    productionReady();
    config(['session.secure' => false]);

    expect(deployResults()['SESSION_SECURE_COOKIE']['status'])->toBe('fail');
});

it('warns when the base domain is not the host of APP_URL', function () {
    productionReady();
    config(['branding.base_domain' => 'clientes.alertprompt.pe']);

    expect(deployResults()['APP_BASE_DOMAIN']['status'])->toBe('warn');
});

it('detects permissions out of sync', function () {
    productionReady();
    Permission::query()->where('name', 'add_ons.request')->delete();

    expect(deployResults()['Permisos'])->toMatchArray(['status' => 'fail'])
        ->and(deployResults()['Permisos']['message'])->toContain('permissions:sync');
});

it('warns when the scheduler stopped beating', function () {
    productionReady();
    Cache::put(DeploymentChecks::HEARTBEAT_KEY, now()->subMinutes(20)->toIso8601String());
    expect(deployResults()['Scheduler']['status'])->toBe('warn');

    Cache::forget(DeploymentChecks::HEARTBEAT_KEY);
    expect(deployResults()['Scheduler']['status'])->toBe('warn');
});

it('records the scheduler heartbeat every minute', function () {
    Cache::forget(DeploymentChecks::HEARTBEAT_KEY);

    $this->artisan('schedule:run')->assertSuccessful();

    expect(Cache::get(DeploymentChecks::HEARTBEAT_KEY))->not->toBeNull();
});

it('skips production rules outside production unless forced', function () {
    config(['app.debug' => true]);

    $results = deployResults(production: false);

    expect($results)->not->toHaveKey('APP_DEBUG')
        ->and($results['Entorno']['status'])->toBe('warn');
});
