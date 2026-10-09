<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

it('lets the client update its own profile but not its document', function () {
    $tenant = Tenant::factory()->create(['document_type' => 'ruc', 'document_number' => '20131312955']);
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->putJson('/api/settings/account', [
        'name' => 'Andina Distribuciones SAC',
        'contact_email' => 'hola@andina.pe',
        'contact_phone' => '+51 1 2223333',
        'address' => 'Jr. Lampa 456',
        'timezone' => 'America/Lima',
        'document_number' => '20100070970',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Andina Distribuciones SAC')
        ->assertJsonPath('data.document_number', '20131312955')
        ->assertJsonPath('data.timezone', 'America/Lima');
});

it('rejects an invalid timezone', function () {
    $this->actingAs(User::factory()->create())->putJson('/api/settings/account', [
        'name' => 'X', 'timezone' => 'Lima/Peru',
    ])->assertUnprocessable()->assertJsonValidationErrors('timezone');
});

it('requires settings.manage to edit', function () {
    $user = User::factory()->withRole('client-user')->create();

    $this->actingAs($user)->putJson('/api/settings/account', ['name' => 'X', 'timezone' => 'America/Lima'])->assertForbidden();
});
