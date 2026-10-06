<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;

it('lists the consents of a contact belonging to the authenticated tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create();
    Consent::factory()->for($contact)->create(['channel' => Channel::WhatsApp]);
    Consent::factory()->for($contact)->create(['channel' => Channel::Email, 'revoked_at' => now()]);

    $response = $this->actingAs($user)->getJson("/api/contacts/{$contact->id}/consents");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('returns 404 for a contact from another tenant', function () {
    $user = User::factory()->create();
    $otherContact = Contact::factory()->create();

    $this->actingAs($user)->getJson("/api/contacts/{$otherContact->id}/consents")->assertNotFound();
});

it('grants a consent with evidence', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create();

    $response = $this->actingAs($user)->postJson("/api/contacts/{$contact->id}/consents", [
        'channel' => 'email',
        'source' => 'formulario web',
        'evidence_text' => 'Acepto recibir comunicaciones de marketing por email.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.channel', 'email')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.source', 'formulario web');

    expect($contact->hasConsentFor('email'))->toBeTrue();
});

it('rejects granting a consent when one is already active for that channel', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create();
    Consent::factory()->for($contact)->create(['channel' => Channel::Sms]);

    $this->actingAs($user)->postJson("/api/contacts/{$contact->id}/consents", [
        'channel' => 'sms',
        'source' => 'formulario web',
        'evidence_text' => 'Acepto recibir SMS.',
    ])->assertStatus(422);
});

it('allows granting a new consent after the previous one was revoked', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create();
    Consent::factory()->for($contact)->create(['channel' => Channel::Sms, 'revoked_at' => now()]);

    $this->actingAs($user)->postJson("/api/contacts/{$contact->id}/consents", [
        'channel' => 'sms',
        'source' => 'formulario web',
        'evidence_text' => 'Acepto recibir SMS de nuevo.',
    ])->assertCreated();
});

it('revokes a consent immediately, keeping the evidence row', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create();
    $consent = Consent::factory()->for($contact)->create(['channel' => Channel::WhatsApp]);

    $response = $this->actingAs($user)->patchJson(
        "/api/contacts/{$contact->id}/consents/{$consent->id}/revoke"
    );

    $response->assertOk()->assertJsonPath('data.is_active', false);

    expect($contact->fresh()->hasConsentFor('whatsapp'))->toBeFalse()
        ->and(Consent::query()->find($consent->id))->not->toBeNull();
});

it('returns 404 when revoking a consent that does not belong to the given contact', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $contact = Contact::factory()->for($tenant)->create();
    $otherContact = Contact::factory()->for($tenant)->create();
    $consent = Consent::factory()->for($otherContact)->create();

    $this->actingAs($user)
        ->patchJson("/api/contacts/{$contact->id}/consents/{$consent->id}/revoke")
        ->assertNotFound();
});
