<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;

it('lists only contacts belonging to the authenticated tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Contact::factory()->for($tenant)->count(3)->create();
    Contact::factory()->for(Tenant::factory()->create())->count(2)->create();

    $response = $this->actingAs($user)->getJson('/api/contacts');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('searches contacts by name, phone or email', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Contact::factory()->for($tenant)->create(['name' => 'Carlos García', 'phone' => '+51911111111']);
    Contact::factory()->for($tenant)->create(['name' => 'Otra Persona', 'phone' => '+51922222222']);

    $response = $this->actingAs($user)->getJson('/api/contacts?search=carlos');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Carlos García');
});

it('creates a contact requiring at least phone or email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/contacts', ['name' => 'Sin contacto'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);

    $response = $this->actingAs($user)->postJson('/api/contacts', [
        'name' => 'Ana Torres',
        'phone' => '+51987654321',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('contacts', ['name' => 'Ana Torres', 'tenant_id' => $user->tenant_id]);
});

it('rejects duplicate phone within the same tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Contact::factory()->for($tenant)->create(['phone' => '+51987654321']);

    $this->actingAs($user)->postJson('/api/contacts', [
        'name' => 'Duplicado',
        'phone' => '+51987654321',
    ])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
});

it('allows the same phone across different tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Contact::factory()->for($tenantA)->create(['phone' => '+51987654321']);
    $userB = User::factory()->for($tenantB)->create();

    $this->actingAs($userB)->postJson('/api/contacts', [
        'name' => 'Mismo Telefono Otro Tenant',
        'phone' => '+51987654321',
    ])->assertCreated();
});

it('updates a contact scoped to the tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create(['name' => 'Nombre Viejo']);

    $this->actingAs($user)->putJson("/api/contacts/{$contact->id}", [
        'name' => 'Nombre Nuevo',
        'phone' => $contact->phone,
        'email' => $contact->email,
    ])->assertOk()->assertJsonPath('data.name', 'Nombre Nuevo');
});

it('returns 404 when updating a contact from another tenant', function () {
    $otherTenantContact = Contact::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson("/api/contacts/{$otherTenantContact->id}", [
        'name' => 'Hackeo',
        'phone' => '+51999999999',
    ])->assertNotFound();
});

it('soft deletes a contact', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create();

    $this->actingAs($user)->deleteJson("/api/contacts/{$contact->id}")->assertNoContent();

    $this->assertSoftDeleted($contact);
});
