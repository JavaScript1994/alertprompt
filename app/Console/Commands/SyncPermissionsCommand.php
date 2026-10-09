<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Authorization\SyncPermissions;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sincroniza la tabla de permisos y los roles de sistema con config/permissions.php.';

    public function handle(SyncPermissions $sync): int
    {
        $result = $sync();

        $this->info("Permisos creados: {$result['created']} · eliminados: {$result['pruned']} · roles de sistema creados: {$result['roles_created']}");

        return self::SUCCESS;
    }
}
