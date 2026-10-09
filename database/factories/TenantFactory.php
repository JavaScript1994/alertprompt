<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
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
            'type' => TenantType::Company,
            'plan' => TenantPlan::Starter,
            'status' => TenantStatus::Active,
            'settings' => [
                'timezone' => 'America/Lima',
            ],
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => ['name' => 'AlertPrompt'])
            ->afterMaking(fn (Tenant $tenant) => $tenant->is_platform = true);
    }

    public function individual(): static
    {
        return $this->state(fn () => ['name' => fake()->name(), 'type' => TenantType::Individual]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
