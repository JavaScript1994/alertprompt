<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el tenant de AlertPrompt y su dueño. Idempotente: si la plataforma ya
 * existe no hace nada, así se puede correr sobre una base con datos.
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        if (Tenant::platform() !== null) {
            return;
        }

        $tenant = new Tenant([
            'name' => 'AlertPrompt',
            'type' => TenantType::Company,
            'plan' => 'empresarial',
            'status' => TenantStatus::Active,
            'settings' => ['timezone' => 'America/Lima'],
        ]);
        $tenant->is_platform = true;
        $tenant->save();

        TenantContext::set($tenant->id);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Administrador General',
            'email' => 'dueno@alertprompt.pe',
            'password' => Hash::make('password'),
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();

        $owner->assignRole('platform-owner');
    }
}
