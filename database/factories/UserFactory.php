<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Por defecto: dueño si el tenant es la plataforma, administrador si es
     * un cliente. Usa withRole() para probar roles con menos permisos.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            setPermissionsTeamId($user->tenant_id);
            $user->assignRole($user->tenant->is_platform ? 'platform-owner' : 'client-admin');
        });
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            setPermissionsTeamId($user->tenant_id);
            $user->syncRoles([$role]);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
