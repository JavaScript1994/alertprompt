<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Authorization\SyncPermissions;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(SyncPermissions $sync): void
    {
        $sync();
    }
}
