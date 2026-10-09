<?php

declare(strict_types=1);

use App\Enums\RoleScope;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

function platformOwner(): User
{
    return User::factory()->for(Tenant::factory()->platform())->create();
}

it('lists the system roles with users and permissions counts', function () {
    $owner = platformOwner();
    User::factory()->count(2)->withRole('client-viewer')->create();

    $response = $this->actingAs($owner)->getJson('/api/admin/roles');

    $response->assertOk();
    $viewer = collect($response->json('data'))->firstWhere('name', 'client-viewer');
    expect($viewer['label'])->toBe('Solo lectura')
        ->and($viewer['is_system'])->toBeTrue()
        ->and($viewer['users_count'])->toBe(2)
        ->and($viewer['permissions_count'])->toBe(5);
});

it('creates a client role with a subset of client permissions', function () {
    $response = $this->actingAs(platformOwner())->postJson('/api/admin/roles', [
        'label' => 'Supervisor de campañas',
        'description' => 'Revisa y dispara campañas.',
        'scope' => 'client',
        'permissions' => ['campaigns.view', 'campaigns.dispatch'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'supervisor-de-campanas')
        ->assertJsonPath('data.scope', 'client')
        ->assertJsonPath('data.is_system', false)
        ->assertJsonPath('data.permissions', ['campaigns.dispatch', 'campaigns.view']);
});

it('gives a fresh role its permissions immediately', function () {
    $this->actingAs(platformOwner())->postJson('/api/admin/roles', [
        'label' => 'Importador',
        'scope' => 'client',
        'permissions' => ['contacts.view', 'contacts.import'],
    ])->assertCreated();

    $user = User::factory()->withRole('importador')->create();

    $this->actingAs($user)->getJson('/api/contacts')->assertOk();
    $this->actingAs($user)->getJson('/api/templates')->assertForbidden();
});

it('rejects admin permissions on a client role', function () {
    $this->actingAs(platformOwner())->postJson('/api/admin/roles', [
        'label' => 'Colado',
        'scope' => 'client',
        'permissions' => ['contacts.view', 'admin.clients.view'],
    ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
});

it('rejects permissions that do not exist', function () {
    $this->actingAs(platformOwner())->postJson('/api/admin/roles', [
        'label' => 'Inventado',
        'scope' => 'platform',
        'permissions' => ['admin.rockets.launch'],
    ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
});

it('rejects a duplicate label within the same panel', function () {
    $this->actingAs(platformOwner())->postJson('/api/admin/roles', [
        'label' => 'Solo lectura',
        'scope' => 'client',
        'permissions' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('label');
});

it('generates a unique key when two labels slug the same', function () {
    $owner = platformOwner();
    $this->actingAs($owner)->postJson('/api/admin/roles', ['label' => 'Soporte', 'scope' => 'platform', 'permissions' => []])->assertCreated();

    $this->actingAs($owner)->postJson('/api/admin/roles', ['label' => 'Soporte', 'scope' => 'client', 'permissions' => []])
        ->assertCreated()
        ->assertJsonPath('data.name', 'soporte-2');
});

it('updates the label and permissions of a system role but not its key', function () {
    $viewer = Role::query()->where('name', 'client-viewer')->firstOrFail();

    $this->actingAs(platformOwner())->putJson("/api/admin/roles/{$viewer->id}", [
        'label' => 'Lector',
        'permissions' => ['contacts.view'],
    ])->assertOk()
        ->assertJsonPath('data.name', 'client-viewer')
        ->assertJsonPath('data.label', 'Lector')
        ->assertJsonPath('data.permissions', ['contacts.view']);
});

it('revokes access right away when a permission is removed from a role', function () {
    $user = User::factory()->withRole('client-viewer')->create();
    $this->actingAs($user)->getJson('/api/templates')->assertOk();

    $viewer = Role::query()->where('name', 'client-viewer')->firstOrFail();
    $this->actingAs(platformOwner())->putJson("/api/admin/roles/{$viewer->id}", [
        'label' => 'Solo lectura',
        'permissions' => ['contacts.view'],
    ])->assertOk();

    $this->actingAs($user->fresh())->getJson('/api/templates')->assertForbidden();
});

it('does not let anyone edit or delete the owner role', function () {
    $ownerRole = Role::query()->where('name', Role::OWNER)->firstOrFail();
    $owner = platformOwner();

    $this->actingAs($owner)->putJson("/api/admin/roles/{$ownerRole->id}", [
        'label' => 'Dueño',
        'permissions' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('role');

    $this->actingAs($owner)->deleteJson("/api/admin/roles/{$ownerRole->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('deletes an unused custom role', function () {
    $role = Role::query()->create([
        'tenant_id' => null, 'name' => 'temporal', 'guard_name' => 'web',
        'label' => 'Temporal', 'scope' => RoleScope::Client,
    ]);

    $this->actingAs(platformOwner())->deleteJson("/api/admin/roles/{$role->id}")->assertNoContent();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

it('refuses to delete a role that is still assigned', function () {
    Role::query()->create([
        'tenant_id' => null, 'name' => 'en-uso', 'guard_name' => 'web',
        'label' => 'En uso', 'scope' => RoleScope::Client,
    ]);
    User::factory()->withRole('en-uso')->create();
    $role = Role::query()->where('name', 'en-uso')->firstOrFail();

    $this->actingAs(platformOwner())->deleteJson("/api/admin/roles/{$role->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('refuses to delete system roles', function () {
    $viewer = Role::query()->where('name', 'client-viewer')->firstOrFail();

    $this->actingAs(platformOwner())->deleteJson("/api/admin/roles/{$viewer->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('needs admin.roles.manage to change roles', function () {
    $reader = Role::query()->create([
        'tenant_id' => null, 'name' => 'auditor', 'guard_name' => 'web',
        'label' => 'Auditor', 'scope' => RoleScope::Platform,
    ]);
    $reader->givePermissionTo('admin.roles.view');
    $platform = Tenant::factory()->platform()->create();
    $auditor = User::factory()->for($platform)->withRole('auditor')->create();

    $this->actingAs($auditor)->getJson('/api/admin/roles')->assertOk();
    $this->actingAs($auditor)->postJson('/api/admin/roles', [
        'label' => 'Nuevo', 'scope' => 'client', 'permissions' => [],
    ])->assertForbidden();
});

it('keeps client administrators out of role management', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/admin/roles')->assertForbidden();
});
