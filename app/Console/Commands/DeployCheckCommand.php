<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Deployment\DeploymentChecks;
use Illuminate\Console\Command;

class DeployCheckCommand extends Command
{
    protected $signature = 'deploy:check {--production : Aplica las reglas de producción aunque APP_ENV no sea production}';

    protected $description = 'Verifica que el despliegue esté listo: migraciones, permisos, variables, Redis, scheduler, Horizon y almacenamiento.';

    public function handle(DeploymentChecks $checks): int
    {
        $results = $checks->run($this->option('production') || app()->isProduction());
        $icons = ['ok' => '<fg=green>✓</>', 'warn' => '<fg=yellow>!</>', 'fail' => '<fg=red>✗</>'];

        $this->table(['', 'Verificación', 'Detalle'], array_map(
            fn (array $result) => [$icons[$result['status']], $result['check'], $result['message']],
            $results,
        ));

        $failed = count(array_filter($results, fn (array $result) => $result['status'] === 'fail'));
        $warned = count(array_filter($results, fn (array $result) => $result['status'] === 'warn'));

        if ($failed > 0) {
            $this->error("{$failed} problema(s) por resolver antes de abrir el servicio.");

            return self::FAILURE;
        }

        $this->info($warned > 0 ? "Listo, con {$warned} aviso(s) para revisar." : 'Todo listo.');

        return self::SUCCESS;
    }
}
