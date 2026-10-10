<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoClientSeeder;
use Illuminate\Support\Facades\Notification;

it('creates the demo company once, with a usable admin and no emails', function () {
    Notification::fake();

    $this->seed(DemoClientSeeder::class);
    $this->seed(DemoClientSeeder::class);

    $tenant = Tenant::query()->where('document_number', DemoClientSeeder::RUC)->sole();
    expect($tenant->plan)->toBe('intermedio')
        ->and(Contact::query()->forTenant($tenant->id)->count())->toBe(12);

    Notification::assertNothingSent();

    $this->postJson('/api/login', ['email' => 'admin@empresaprueba.pe', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.tenant.name', 'Empresa de Prueba SAC')
        ->assertJsonPath('data.roles.0.label', 'Administrador');

    expect(User::query()->where('email', 'admin@empresaprueba.pe')->count())->toBe(1);
});
