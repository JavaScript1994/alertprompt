<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

it('logs in a user without MFA with valid credentials and scopes the response to their tenant', function () {
    $tenant = Tenant::factory()->create(['name' => 'Demo Empresa SAC']);
    // Con MFA enrolado la contraseña no abre sesión (ver tests/Feature/Mfa).
    $user = User::factory()->withoutMfa()->for($tenant)->create([
        'email' => 'admin@demo.pe',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@demo.pe',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'admin@demo.pe')
        ->assertJsonPath('data.tenant.name', 'Demo Empresa SAC');

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'admin@demo.pe']);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@demo.pe',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('returns the authenticated user on /api/user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

it('rejects unauthenticated access to /api/user', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('logs out the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/logout');

    $response->assertNoContent();

    // 'web' explícito: auth:sanctum deja 'sanctum' como guard default del
    // proceso (Auth::shouldUse), y el RequestGuard de sanctum cachea el
    // usuario resuelto antes del logout — assertGuest() sin guard checkearía
    // esa caché stale, no el logout real hecho sobre el guard de sesión.
    $this->assertGuest('web');
});
