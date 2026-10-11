<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    protected static ?string $mfaSecret = null;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Por defecto con MFA enrolado, como cualquier usuario real tras su
            // primer login. withoutMfa() para probar el enrolamiento.
            'two_factor_secret' => static::$mfaSecret ??= (new Google2FA)->generateSecretKey(32),
            'two_factor_confirmed_at' => now(),
        ];
    }

    public function withoutMfa(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
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
