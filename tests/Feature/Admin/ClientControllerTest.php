<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SetPasswordLink;
use Illuminate\Support\Facades\Notification;

function companyPayload(array $overrides = []): array
{
    return [
        'type' => 'company',
        'name' => 'Distribuidora Andina SAC',
        'document_type' => 'ruc',
        'document_number' => '20131312955',
        'contact_email' => 'contacto@andina.pe',
        'contact_phone' => '+51 1 4445555',
        'admin_name' => 'Rosa Quispe',
        'admin_email' => 'rosa@andina.pe',
        ...$overrides,
    ];
}

it('creates a company with its initial administrator and sends the invitation', function () {
    Notification::fake();

    $response = $this->actingAs(platformOwner())->postJson('/api/admin/clients', companyPayload());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Distribuidora Andina SAC')
        ->assertJsonPath('data.type', 'company')
        ->assertJsonPath('data.document_number', '20131312955')
        ->assertJsonPath('data.users_count', 1);

    $tenant = Tenant::query()->findOrFail($response->json('data.id'));
    $admin = User::query()->forTenant($tenant->id)->where('email', 'rosa@andina.pe')->firstOrFail();

    setPermissionsTeamId($tenant->id);
    expect($admin->hasRole('client-admin'))->toBeTrue();

    Notification::assertSentTo($admin, SetPasswordLink::class, fn (SetPasswordLink $n) => $n->invitation
        && $n->tenantName === 'Distribuidora Andina SAC');

    expect(AuditLog::query()->where('action', 'client.created')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('creates a natural person client with DNI', function () {
    Notification::fake();

    $this->actingAs(platformOwner())->postJson('/api/admin/clients', companyPayload([
        'type' => 'individual',
        'name' => 'Lucía Torres',
        'document_type' => 'dni',
        'document_number' => '46779354',
        'admin_name' => 'Lucía Torres',
        'admin_email' => 'lucia@gmail.com',
    ]))->assertCreated()->assertJsonPath('data.type', 'individual');
});

it('validates peruvian documents', function (array $overrides, string $field) {
    $this->actingAs(platformOwner())->postJson('/api/admin/clients', companyPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'RUC con dígito errado' => [['document_number' => '20131312956'], 'document_number'],
    'RUC 10 para empresa' => [['document_number' => '10467793549'], 'document_number'],
    'DNI para empresa' => [['document_type' => 'dni', 'document_number' => '46779354'], 'document_type'],
    'DNI corto' => [['type' => 'individual', 'document_type' => 'dni', 'document_number' => '4677935'], 'document_number'],
]);

it('rejects a document or admin email that already exists', function () {
    Notification::fake();
    $owner = platformOwner();
    $this->actingAs($owner)->postJson('/api/admin/clients', companyPayload())->assertCreated();

    $this->actingAs($owner)->postJson('/api/admin/clients', companyPayload(['admin_email' => 'otro@andina.pe']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');

    $this->actingAs($owner)->postJson('/api/admin/clients', companyPayload(['document_number' => '20100070970']))
        ->assertUnprocessable()->assertJsonValidationErrors('admin_email');
});

it('lists clients with counts across tenants, without the platform itself', function () {
    $owner = platformOwner();
    $client = Tenant::factory()->create(['name' => 'Cliente Uno']);
    Contact::factory()->for($client)->count(3)->create();
    Tenant::factory()->individual()->create();

    $response = $this->actingAs($owner)->getJson('/api/admin/clients?type=company');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Cliente Uno')
        ->and($response->json('data.0.contacts_count'))->toBe(3);
});

it('updates the client profile but never its type', function () {
    $client = Tenant::factory()->create(['document_type' => 'ruc', 'document_number' => '20131312955']);

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$client->id}", [
        'type' => 'individual',
        'name' => 'Nuevo Nombre SAC',
        'document_type' => 'ruc',
        'document_number' => '20131312955',
        'address' => 'Av. Arequipa 123, Lima',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Nuevo Nombre SAC')
        ->assertJsonPath('data.type', 'company')
        ->assertJsonPath('data.address', 'Av. Arequipa 123, Lima');
});

it('suspends and reactivates a client, cutting off its users meanwhile', function () {
    $owner = platformOwner();
    $client = Tenant::factory()->create();
    $clientUser = User::factory()->for($client)->create();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/suspend", ['reason' => 'Falta de pago'])
        ->assertOk()->assertJsonPath('data.status', 'suspended');
    $this->actingAs($clientUser->fresh())->getJson('/api/contacts')->assertForbidden();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/reactivate")
        ->assertOk()->assertJsonPath('data.status', 'active');
    expect($client->fresh()->status)->toBe(TenantStatus::Active);

    expect(AuditLog::query()->where('tenant_id', $client->id)->pluck('action')->all())
        ->toContain('client.suspended', 'client.reactivated');
});

it('lists the users of a client with their roles in that client', function () {
    $client = Tenant::factory()->create();
    User::factory()->for($client)->create(['name' => 'Ana']);
    User::factory()->for($client)->withRole('client-viewer')->create(['name' => 'Beto']);

    $response = $this->actingAs(platformOwner())->getJson("/api/admin/clients/{$client->id}/users");

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Ana')
        ->assertJsonPath('data.0.roles.0.label', 'Administrador')
        ->assertJsonPath('data.1.roles.0.label', 'Solo lectura');
});

it('does not expose the platform tenant as a client', function () {
    $owner = platformOwner();

    $this->actingAs($owner)->getJson("/api/admin/clients/{$owner->tenant_id}")->assertNotFound();
});

it('keeps client users out of client management', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/admin/clients')->assertForbidden();
});
