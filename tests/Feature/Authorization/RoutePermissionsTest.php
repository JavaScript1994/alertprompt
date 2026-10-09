<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;

it('lets a read-only user list contacts but not create them', function () {
    $user = User::factory()->withRole('client-viewer')->create();

    $this->actingAs($user)->getJson('/api/contacts')->assertOk();
    $this->actingAs($user)->postJson('/api/contacts', [
        'name' => 'Ana',
        'phone' => '+51999888777',
    ])->assertForbidden();
});

it('does not let a regular user delete templates', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->withRole('client-user')->create();
    $template = Template::factory()->for($tenant)->create();

    $this->actingAs($user)->deleteJson("/api/templates/{$template->id}")->assertForbidden();

    expect(Template::query()->whereKey($template->id)->exists())->toBeTrue();
});

it('does not let a read-only user dispatch a campaign', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->withRole('client-viewer')->create();
    $campaign = Campaign::factory()->for($tenant)->create();

    $this->actingAs($user)->postJson("/api/campaigns/{$campaign->id}/dispatch")->assertForbidden();
});

it('does not let a read-only user revoke consents', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->withRole('client-viewer')->create();
    $contact = Contact::factory()->for($tenant)->create();

    $this->actingAs($user)->getJson("/api/contacts/{$contact->id}/consents")->assertOk();
    $this->actingAs($user)->postJson("/api/contacts/{$contact->id}/consents", [
        'channel' => 'sms',
        'source' => 'web_form',
        'evidence_text' => 'Acepto',
    ])->assertForbidden();
});

it('returns roles, permissions and tenant kind on /api/user', function () {
    $user = User::factory()->withRole('client-viewer')->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('data.roles.0.name', 'client-viewer')
        ->assertJsonPath('data.roles.0.label', 'Solo lectura')
        ->assertJsonPath('data.tenant.is_platform', false)
        ->assertJsonPath('data.tenant.type', 'company');

    expect($response->json('data.permissions'))
        ->toContain('contacts.view')
        ->not->toContain('contacts.create');
});
