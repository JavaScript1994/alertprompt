<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('imports contacts from a csv, creating new and updating existing ones, skipping invalid rows', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    $existing = Contact::factory()->for($tenant)->create([
        'name' => 'Nombre Viejo',
        'phone' => '+51911111111',
        'email' => 'viejo@example.com',
    ]);

    $csv = <<<'CSV'
    name,phone,email,distrito
    Nombre Viejo Actualizado,+51911111111,viejo@example.com,Miraflores
    Contacto Nuevo,+51922222222,nuevo@example.com,Surco
    ,+51933333333,,San Isidro
    CSV;

    $file = UploadedFile::fake()->createWithContent('contactos.csv', $csv);

    $response = $this->actingAs($user)->postJson('/api/contacts/import', ['file' => $file]);

    $response->assertAccepted();

    expect(Contact::query()->count())->toBe(2);

    $existing->refresh();
    expect($existing->name)->toBe('Nombre Viejo Actualizado')
        ->and($existing->attributes['distrito'])->toBe('Miraflores');

    $this->assertDatabaseHas('contacts', [
        'tenant_id' => $tenant->id,
        'name' => 'Contacto Nuevo',
        'phone' => '+51922222222',
    ]);
});

it('does not import contacts across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->for($tenantA)->create();
    Contact::factory()->for($tenantB)->create(['phone' => '+51911111111', 'email' => 'x@example.com']);

    $csv = <<<'CSV'
    name,phone,email
    Nombre A,+51911111111,x@example.com
    CSV;

    $file = UploadedFile::fake()->createWithContent('contactos.csv', $csv);

    $this->actingAs($userA)->postJson('/api/contacts/import', ['file' => $file])->assertAccepted();

    expect(Contact::query()->where('tenant_id', $tenantA->id)->count())->toBe(1);
});

it('rejects a non-csv file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('contactos.pdf', 10);

    $this->actingAs($user)->postJson('/api/contacts/import', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});
