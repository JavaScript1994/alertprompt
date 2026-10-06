<?php

declare(strict_types=1);

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;

it('lists only templates belonging to the authenticated tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Template::factory()->for($tenant)->count(2)->create();
    Template::factory()->for(Tenant::factory()->create())->create();

    $response = $this->actingAs($user)->getJson('/api/templates');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('creates a template deriving variables from the body and defaulting status to draft', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/templates', [
        'channel' => 'whatsapp',
        'category' => 'marketing',
        'name' => 'Bienvenida',
        'body' => 'Hola {{nombre}}, bienvenido a {{empresa}}.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', TemplateStatus::Draft->value)
        ->assertJsonPath('data.variables', ['nombre', 'empresa'])
        ->assertJsonPath('data.requires_consent', true);
});

it('ignores a client-provided status on creation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/templates', [
        'channel' => 'sms',
        'category' => 'utility',
        'name' => 'Confirmación',
        'body' => 'Tu código es {{codigo}}.',
        'status' => 'approved',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', TemplateStatus::Draft->value);
});

it('rejects duplicate template name within the same tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Template::factory()->for($tenant)->create(['name' => 'Bienvenida']);

    $this->actingAs($user)->postJson('/api/templates', [
        'channel' => 'whatsapp',
        'category' => 'marketing',
        'name' => 'Bienvenida',
        'body' => 'Hola {{nombre}}.',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
});

it('updates a template and recomputes its variables', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $template = Template::factory()->for($tenant)->create([
        'body' => 'Hola {{nombre}}.',
        'variables' => ['nombre'],
    ]);

    $response = $this->actingAs($user)->putJson("/api/templates/{$template->id}", [
        'channel' => $template->channel->value,
        'category' => $template->category->value,
        'name' => $template->name,
        'body' => 'Hola {{nombre}}, tu pedido {{pedido}} llegó.',
        'status' => 'approved',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.variables', ['nombre', 'pedido'])
        ->assertJsonPath('data.status', 'approved');
});

it('returns 404 when updating a template from another tenant', function () {
    $otherTemplate = Template::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson("/api/templates/{$otherTemplate->id}", [
        'channel' => $otherTemplate->channel->value,
        'category' => $otherTemplate->category->value,
        'name' => 'Hackeo',
        'body' => 'Hola.',
    ])->assertNotFound();
});

it('soft deletes a template', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $template = Template::factory()->for($tenant)->create();

    $this->actingAs($user)->deleteJson("/api/templates/{$template->id}")->assertNoContent();

    $this->assertSoftDeleted($template);
});

it('previews a template body with sample data, flagging missing variables', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/templates/preview', [
        'body' => 'Hola {{nombre}}, tu pedido {{pedido}} está listo.',
        'sample_data' => ['nombre' => 'Carlos'],
    ]);

    $response->assertOk()
        ->assertJson([
            'variables' => ['nombre', 'pedido'],
            'missing' => ['pedido'],
            'rendered' => 'Hola Carlos, tu pedido {{pedido}} está listo.',
        ]);
});
