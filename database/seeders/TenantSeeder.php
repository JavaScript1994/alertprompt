<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Empresa SAC',
            'plan' => TenantPlan::Growth,
            'settings' => [
                'timezone' => 'America/Lima',
                'whatsapp_daily_limit' => 1000,
            ],
        ]);

        TenantContext::set($tenant->id);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Demo',
            'email' => 'admin@demo.pe',
            'password' => Hash::make('password'),
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $admin->assignRole('client-admin');
    }
}
