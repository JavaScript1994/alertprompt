<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' SAC',
            'plan' => TenantPlan::Starter,
            'settings' => [
                'timezone' => 'America/Lima',
            ],
        ];
    }
}
