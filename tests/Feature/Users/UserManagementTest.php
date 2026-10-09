<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SetPasswordLink;
use Illuminate\Support\Facades\Notification;

function roleId(string $name): int
{
    return Role::query()->whereNull('tenant_id')->where('name', $name)->value('id');
}

it('lists only the users of the own tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create(['name' => 'Ana']);
    User::factory()->for($tenant)->withRole('client-user')->create(['name' => 'Beto']);
    User::factory()->create(['name' => 'Otro tenant']);

    $response = $this->actingAs($admin)->getJson('/api/users');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Ana', 'Beto'])
        ->and($response->json('data.1.roles.0.label'))->toBe('Usuario');
});

it('offers only client roles to a client and platform roles to the platform', function () {
    $client = $this->actingAs(User::factory()->create())->getJson('/api/users/roles')->json('data');
    $platform = $this->actingAs(platformOwner())->getJson('/api/users/roles')->json('data');

    expect(collect($client)->pluck('name'))->toContain('client-admin', 'client-user', 'client-viewer')->not->toContain('platform-owner')
        ->and(collect($platform)->pluck('name'))->toContain('platform-owner')->not->toContain('client-admin');
});

it('invites a user with a role and sends the invitation', function () {
    Notification::fake();
    $tenant = Tenant::factory()->create(['name' => 'Andina SAC']);
    $admin = User::factory()->for($tenant)->create();

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Carla Ríos',
        'email' => 'Carla@Andina.pe',
        'role_id' => roleId('client-user'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'carla@andina.pe')
        ->assertJsonPath('data.roles.0.name', 'client-user')
        ->assertJsonPath('data.email_verified_at', null);

    $invited = User::query()->where('email', 'carla@andina.pe')->firstOrFail();
    expect($invited->tenant_id)->toBe($tenant->id);
    Notification::assertSentTo($invited, SetPasswordLink::class, fn (SetPasswordLink $n) => $n->invitation && $n->tenantName === 'Andina SAC');
});

it('rejects assigning a platform role from a client account', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/users', [
        'name' => 'Intruso',
        'email' => 'intruso@demo.pe',
        'role_id' => roleId('platform-owner'),
    ])->assertUnprocessable()->assertJsonValidationErrors('role_id');
});

it('changes the role of a user', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();
    $user = User::factory()->for($tenant)->withRole('client-user')->create();

    $this->actingAs($admin)->putJson("/api/users/{$user->id}", ['name' => 'Renombrado', 'role_id' => roleId('client-viewer')])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renombrado')
        ->assertJsonPath('data.roles.0.name', 'client-viewer');

    $this->actingAs($user->fresh())->postJson('/api/contacts', ['name' => 'X', 'phone' => '+51999000111'])->assertForbidden();
});

it('never leaves an account without someone who can manage users', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();
    $other = User::factory()->for($tenant)->create();

    // Puede degradar a otro administrador mientras quede uno.
    $this->actingAs($admin)->putJson("/api/users/{$other->id}", ['name' => $other->name, 'role_id' => roleId('client-user')])->assertOk();

    // Pero no puede quitarse a sí mismo el permiso.
    $this->actingAs($admin)->putJson("/api/users/{$admin->id}", ['name' => $admin->name, 'role_id' => roleId('client-user')])
        ->assertUnprocessable()->assertJsonValidationErrors('role_id');
});

it('deactivates a user and blocks their access and login', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();
    $user = User::factory()->for($tenant)->withRole('client-user')->create(['email' => 'beto@demo.pe', 'password' => bcrypt('password')]);

    $this->actingAs($admin)->postJson("/api/users/{$user->id}/deactivate")->assertOk()->assertJsonPath('data.deactivated_at', fn ($v) => $v !== null);

    // Cambio de usuario dentro del test: AuthenticateSession compara el hash
    // de contraseña guardado en la sesión del admin, así que se empieza limpia.
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs($user->fresh())->getJson('/api/contacts')->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->postJson('/api/login', ['email' => 'beto@demo.pe', 'password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs($admin)->postJson("/api/users/{$user->id}/reactivate")->assertOk()->assertJsonPath('data.deactivated_at', null);
    expect(AuditLog::query()->where('tenant_id', $tenant->id)->pluck('action')->all())->toContain('user.deactivated', 'user.reactivated');
});

it('does not let users deactivate themselves', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->postJson("/api/users/{$admin->id}/deactivate")
        ->assertUnprocessable()->assertJsonValidationErrors('user');
});

it('cannot touch users of another tenant', function () {
    $admin = User::factory()->create();
    $foreign = User::factory()->create();

    $this->actingAs($admin)->postJson("/api/users/{$foreign->id}/deactivate")->assertNotFound();
});

it('resends a pending invitation only', function () {
    Notification::fake();
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();
    $pending = User::factory()->for($tenant)->unverified()->create();

    $this->actingAs($admin)->postJson("/api/users/{$pending->id}/resend-invitation")->assertNoContent();
    Notification::assertSentTo($pending, SetPasswordLink::class);

    $this->actingAs($admin)->postJson("/api/users/{$admin->id}/resend-invitation")->assertUnprocessable();
});

it('requires users.manage to invite', function () {
    $viewer = User::factory()->withRole('client-viewer')->create();

    $this->actingAs($viewer)->getJson('/api/users')->assertForbidden();

    $user = User::factory()->withRole('client-user')->create();
    $this->actingAs($user)->postJson('/api/users', ['name' => 'X', 'email' => 'x@demo.pe', 'role_id' => roleId('client-user')])->assertForbidden();
});

it('lets support manage users of a client in support mode', function () {
    Notification::fake();
    $client = Tenant::factory()->create();
    User::factory()->for($client)->create();

    $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$client->id}/impersonate")->assertNoContent();

    $this->postJson('/api/users', ['name' => 'Nueva', 'email' => 'nueva@cliente.pe', 'role_id' => roleId('client-user')])
        ->assertCreated();

    expect(User::query()->forTenant($client->id)->where('email', 'nueva@cliente.pe')->exists())->toBeTrue();
});
