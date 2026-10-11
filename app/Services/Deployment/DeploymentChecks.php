<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Services\Authorization\PermissionRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Spatie\Permission\Models\Permission;
use Throwable;

/**
 * Verificaciones de un despliegue (`php artisan deploy:check`). Cada una
 * devuelve ok, warn (revisar) o fail (no está listo). Con $production se
 * aplican las reglas de producción aunque APP_ENV diga otra cosa.
 */
class DeploymentChecks
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    /** Tareas que deben estar programadas (routes/console.php). */
    private const SCHEDULED = ['campaigns:dispatch-scheduled', 'memberships:refresh', 'announcements:send-emails', 'billing:daily'];

    public function __construct(
        private readonly PermissionRegistry $permissions,
        private readonly Schedule $schedule,
    ) {}

    /** @return list<array{check: string, status: 'ok'|'warn'|'fail', message: string}> */
    public function run(bool $production): array
    {
        return array_merge(
            [$this->database(), $this->migrations(), $this->permissionsSynced()],
            $this->environment($production),
            [$this->redis(), $this->scheduledTasks(), $this->schedulerHeartbeat(), $this->horizon(), $this->storage()],
        );
    }

    private function database(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->ok('Base de datos', 'Conectada ('.DB::connection()->getDriverName().').');
        } catch (Throwable $e) {
            return $this->fail('Base de datos', 'Sin conexión: '.$e->getMessage());
        }
    }

    private function migrations(): array
    {
        try {
            $migrator = app('migrator');
            if (! $migrator->repositoryExists()) {
                return $this->fail('Migraciones', 'No hay tabla de migraciones: corre php artisan migrate.');
            }
            $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
            $pending = array_values(array_diff($files, $migrator->getRepository()->getRan()));

            return $pending === []
                ? $this->ok('Migraciones', 'Todas aplicadas.')
                : $this->fail('Migraciones', count($pending).' pendiente(s): corre php artisan migrate ('.implode(', ', array_slice($pending, 0, 3)).(count($pending) > 3 ? '…' : '').').');
        } catch (Throwable $e) {
            return $this->fail('Migraciones', 'No se pudo revisar: '.$e->getMessage());
        }
    }

    private function permissionsSynced(): array
    {
        try {
            $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
            $missing = array_diff($this->permissions->all(), $existing);
            $stale = array_diff($existing, $this->permissions->all());

            return $missing === [] && $stale === []
                ? $this->ok('Permisos', 'Sincronizados con config/permissions.php.')
                : $this->fail('Permisos', count($missing).' por crear y '.count($stale).' por quitar: corre php artisan permissions:sync.');
        } catch (Throwable $e) {
            return $this->fail('Permisos', 'No se pudo revisar: '.$e->getMessage());
        }
    }

    /** @return list<array{check: string, status: 'ok'|'warn'|'fail', message: string}> */
    private function environment(bool $production): array
    {
        $appUrl = (string) config('app.url');
        $https = str_starts_with($appUrl, 'https://');
        $baseDomain = (string) config('branding.base_domain');
        $results = [];

        $results[] = config('app.key') ? $this->ok('APP_KEY', 'Definida.') : $this->fail('APP_KEY', 'Vacía: php artisan key:generate.');

        if (! $production) {
            $results[] = $this->warn('Entorno', 'APP_ENV='.app()->environment().': las reglas de producción no se aplican (usa --production para forzarlas).');

            return $results;
        }

        $results[] = config('app.debug') ? $this->fail('APP_DEBUG', 'Activo en producción: expone errores y datos. Pon APP_DEBUG=false.') : $this->ok('APP_DEBUG', 'Desactivado.');
        $results[] = $https ? $this->ok('APP_URL', $appUrl) : $this->fail('APP_URL', "Debe usar https:// en producción (hoy: {$appUrl}).");
        $results[] = $baseDomain === '' || $baseDomain === 'localhost' || str_ends_with($baseDomain, '.localhost')
            ? $this->fail('APP_BASE_DOMAIN', "Es «{$baseDomain}»: los logins por empresa no funcionarán. Pon APP_BASE_DOMAIN=alertprompt.pe.")
            : (parse_url($appUrl, PHP_URL_HOST) === $baseDomain
                ? $this->ok('APP_BASE_DOMAIN', "{$baseDomain} (requiere DNS y certificado comodín *.{$baseDomain}).")
                : $this->warn('APP_BASE_DOMAIN', "{$baseDomain} no coincide con el host de APP_URL: el login general y los de empresa quedarían en dominios distintos."));
        $results[] = config('session.domain')
            ? $this->fail('SESSION_DOMAIN', 'Debe quedar vacío: así cada empresa tiene su propia cookie de sesión (§2.5).')
            : $this->ok('SESSION_DOMAIN', 'Vacío (una cookie por subdominio).');
        $results[] = config('session.secure') || ! $https
            ? $this->ok('SESSION_SECURE_COOKIE', config('session.secure') ? 'Activo.' : 'No aplica sin https.')
            : $this->fail('SESSION_SECURE_COOKIE', 'Con https debe ser true.');
        $results[] = config('queue.default') === 'sync'
            ? $this->fail('QUEUE_CONNECTION', 'Es sync: los envíos correrían dentro de la petición. Usa redis.')
            : $this->ok('QUEUE_CONNECTION', (string) config('queue.default'));
        $results[] = in_array(config('mail.default'), ['log', 'array'], true)
            ? $this->fail('MAIL_MAILER', 'Es '.config('mail.default').': los correos (MFA, invitaciones, incidentes) no salen.')
            : $this->ok('MAIL_MAILER', (string) config('mail.default'));

        return $results;
    }

    private function redis(): array
    {
        if (config('queue.default') !== 'redis' && config('cache.default') !== 'redis') {
            return $this->ok('Redis', 'No se usa para cola ni caché.');
        }

        try {
            Redis::connection()->ping();

            return $this->ok('Redis', 'Responde.');
        } catch (Throwable $e) {
            return $this->fail('Redis', 'Sin conexión: '.$e->getMessage());
        }
    }

    private function scheduledTasks(): array
    {
        $commands = collect($this->schedule->events())->map(fn (Event $event) => (string) $event->command)->implode(' ');
        $missing = array_values(array_filter(self::SCHEDULED, fn (string $command) => ! str_contains($commands, $command)));

        return $missing === []
            ? $this->ok('Tareas programadas', implode(', ', self::SCHEDULED).'.')
            : $this->fail('Tareas programadas', 'Faltan en routes/console.php: '.implode(', ', $missing).'.');
    }

    /** El scheduler escribe un latido cada minuto; si no hay uno reciente, el cron no está corriendo. */
    private function schedulerHeartbeat(): array
    {
        try {
            $last = Cache::get(self::HEARTBEAT_KEY);
        } catch (Throwable $e) {
            return $this->fail('Scheduler', 'No se pudo leer la caché: '.$e->getMessage());
        }

        if ($last === null) {
            return $this->warn('Scheduler', 'Sin latidos: configura el cron (* * * * * php artisan schedule:run) o el contenedor scheduler. Puede tardar un minuto tras el despliegue.');
        }

        $minutes = (int) CarbonImmutable::parse($last)->diffInMinutes(CarbonImmutable::now());

        return $minutes <= 5
            ? $this->ok('Scheduler', "Último latido hace {$minutes} min.")
            : $this->warn('Scheduler', "Último latido hace {$minutes} min: revisa que el cron siga corriendo.");
    }

    private function horizon(): array
    {
        if (config('queue.default') !== 'redis') {
            return $this->ok('Horizon', 'La cola no usa redis.');
        }

        try {
            return app(MasterSupervisorRepository::class)->all() === []
                ? $this->warn('Horizon', 'No hay workers corriendo: inicia php artisan horizon (los envíos y correos quedan en cola).')
                : $this->ok('Horizon', 'Corriendo.');
        } catch (Throwable $e) {
            return $this->warn('Horizon', 'No se pudo revisar: '.$e->getMessage());
        }
    }

    private function storage(): array
    {
        $paths = [storage_path('app'), storage_path('logs'), storage_path('framework/cache'), base_path('bootstrap/cache')];
        $blocked = array_values(array_filter($paths, fn (string $path) => ! is_writable($path)));

        return $blocked === []
            ? $this->ok('Almacenamiento', 'storage/ y bootstrap/cache escribibles.')
            : $this->fail('Almacenamiento', 'Sin permiso de escritura: '.implode(', ', $blocked).' (chown al usuario de php-fpm).');
    }

    private function ok(string $check, string $message): array
    {
        return ['check' => $check, 'status' => 'ok', 'message' => $message];
    }

    private function warn(string $check, string $message): array
    {
        return ['check' => $check, 'status' => 'warn', 'message' => $message];
    }

    private function fail(string $check, string $message): array
    {
        return ['check' => $check, 'status' => 'fail', 'message' => $message];
    }
}
