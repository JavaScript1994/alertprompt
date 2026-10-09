<?php

declare(strict_types=1);

use App\Enums\RoleScope;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;

it('lets the platform owner read the permission tree', function () {
    $owner = User::factory()->for(Tenant::factory()->platform())->create();

    $response = $this->actingAs($owner)->getJson('/api/admin/permissions');

    $response->assertOk()
        ->assertJsonPath('data.0.key', 'admin')
        ->assertJsonPath('data.0.scope', 'platform')
        ->assertJsonPath('data.1.modules.1.key', 'contacts')
        ->assertJsonPath('data.1.modules.1.permissions.0.name', 'contacts.view');
});

it('blocks client administrators from the admin API', function () {
    $clientAdmin = User::factory()->create();

    $this->actingAs($clientAdmin)->getJson('/api/admin/permissions')->assertForbidden();
});

it('blocks client users even if their role somehow had admin permissions', function () {
    $role = Role::query()->create([
        'tenant_id' => null,
        'name' => 'misconfigured',
        'guard_name' => 'web',
        'label' => 'Mal configurado',
        'scope' => RoleScope::Client,
    ]);
    $role->givePermissionTo('admin.roles.view');
    $user = User::factory()->withRole('misconfigured')->create();

    $this->actingAs($user)->getJson('/api/admin/permissions')->assertForbidden();
});

it('requires the specific admin permission inside the platform tenant', function () {
    $platform = Tenant::factory()->platform()->create();
    $operator = User::factory()->for($platform)->withRole('client-user')->create();

    $this->actingAs($operator)->getJson('/api/admin/permissions')->assertForbidden();
});

it('gives the general administrator no messaging panel of its own', function () {
    $owner = User::factory()->for(Tenant::factory()->platform())->create();

    foreach (['/api/campaigns', '/api/contacts', '/api/templates', '/api/reports', '/api/billing/invoices', '/api/membership'] as $url) {
        $this->actingAs($owner)->getJson($url)->assertForbidden();
    }

    // Su equipo sí lo gestiona desde la cuenta de la plataforma.
    $this->actingAs($owner)->getJson('/api/users')->assertOk();
});

it('reaches the client panel only in support mode', function () {
    $owner = User::factory()->for(Tenant::factory()->platform())->create();
    $client = Tenant::factory()->create();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/impersonate")->assertNoContent();

    $this->getJson('/api/campaigns')->assertOk();
});

it('names the owner role "Administrador general"', function () {
    $owner = User::factory()->for(Tenant::factory()->platform())->create();

    $this->actingAs($owner)->getJson('/api/user')->assertJsonPath('data.roles.0.label', 'Administrador general');
});

it('allows only one platform tenant', function () {
    Tenant::factory()->platform()->create();
    Tenant::factory()->platform()->create();
})->throws(QueryException::class);

it('rejects login for a suspended tenant', function () {
    User::factory()->for(Tenant::factory()->suspended())->create([
        'email' => 'ana@suspendida.pe',
        'password' => bcrypt('password'),
    ]);

    $this->postJson('/api/login', ['email' => 'ana@suspendida.pe', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest('web');
});

it('cuts off open sessions when the tenant gets suspended', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $tenant->update(['status' => 'suspended']);

    $this->actingAs($user->fresh())->getJson('/api/contacts')->assertForbidden();
});
