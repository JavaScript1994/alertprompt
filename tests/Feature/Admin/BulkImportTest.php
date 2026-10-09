<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\SuppressionReason;
use App\Jobs\ImportBulkContacts;
use App\Models\AuditLog;
use App\Models\BulkImport;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SuppressionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Storage::fake('local'));

const BULK_HEADER = "name,phone,email,distrito,consent_channels,consent_granted_at,consent_source,consent_evidence_text,consent_ip\n";

function uploadBulk(Tenant $client, string $rows, array $overrides = []): TestResponse
{
    return test()->actingAs(platformOwner())->post('/api/admin/bulk-imports', [
        'client_id' => $client->id,
        'file' => UploadedFile::fake()->createWithContent('base.csv', BULK_HEADER.$rows),
        'declared_source' => 'Formulario de suscripción del sitio web, campaña de verano 2026',
        'attestation' => '1',
        ...$overrides,
    ], ['Accept' => 'application/json']);
}

function contactsOf(Tenant $tenant): Builder
{
    return Contact::query()->forTenant($tenant->id);
}

it('imports contacts and records consent only with complete evidence', function () {
    $client = Tenant::factory()->create();
    $rows = implode("\n", [
        'Ana,+51911111111,ana@mail.pe,Miraflores,whatsapp|sms,2026-05-10,web_form,"Acepto recibir ofertas por WhatsApp y SMS",190.1.1.1',
        'Beto,+51922222222,,Surco,,,,,',
        'Carla,+51933333333,,Lince,whatsapp,2026-05-10,web_form,,',
    ])."\n";

    $response = uploadBulk($client, $rows)->assertStatus(202);
    $import = BulkImport::query()->findOrFail($response->json('data.id'));

    expect($import->status)->toBe('completed')
        ->and($import->only(['rows', 'created', 'consents_recorded', 'without_consent', 'invalid']))
        ->toBe(['rows' => 3, 'created' => 3, 'consents_recorded' => 2, 'without_consent' => 2, 'invalid' => 0]);

    $ana = contactsOf($client)->where('phone', '+51911111111')->firstOrFail();
    expect($ana->attributes)->toBe(['distrito' => 'Miraflores'])
        ->and($ana->hasConsentFor('whatsapp'))->toBeTrue()
        ->and($ana->hasConsentFor('sms'))->toBeTrue();

    $consent = Consent::query()->where('contact_id', $ana->id)->where('channel', 'whatsapp')->firstOrFail();
    expect($consent->source)->toBe("carga_masiva#{$import->id}: web_form")
        ->and($consent->evidence_text)->toBe('Acepto recibir ofertas por WhatsApp y SMS')
        ->and($consent->ip)->toBe('190.1.1.1');

    // Carla trae evidencia incompleta: se importa, sin consentimiento, y se reporta.
    expect(contactsOf($client)->where('phone', '+51933333333')->firstOrFail()->hasConsentFor('whatsapp'))->toBeFalse()
        ->and(collect($import->errors)->pluck('message')->implode(' '))->toContain('Falta el texto que aceptó el contacto');
});

it('never re-grants consent to someone who unsubscribed', function () {
    $client = Tenant::factory()->create();
    app(SuppressionService::class)->suppress($client->id, Channel::WhatsApp, '+51911111111', SuppressionReason::Unsubscribed);

    $response = uploadBulk($client, "Ana,+51911111111,,,whatsapp,2026-05-10,web_form,Acepto,\n");
    $import = BulkImport::query()->findOrFail($response->json('data.id'));

    expect($import->consents_blocked)->toBe(1)->and($import->consents_recorded)->toBe(0)
        ->and(contactsOf($client)->firstOrFail()->hasConsentFor('whatsapp'))->toBeFalse();
});

it('does not override a revocation that happened after the declared consent date', function () {
    $client = Tenant::factory()->create();
    $contact = Contact::factory()->for($client)->create(['phone' => '+51911111111']);
    Consent::factory()->for($contact)->create(['channel' => 'sms', 'granted_at' => '2026-01-01', 'revoked_at' => '2026-06-01']);

    $import = BulkImport::query()->findOrFail(
        uploadBulk($client, "Ana,+51911111111,,,sms,2026-05-10,web_form,Acepto,\n")->json('data.id'),
    );

    expect($import->consents_blocked)->toBe(1)->and($contact->fresh()->hasConsentFor('sms'))->toBeFalse();
});

it('rejects invalid rows without stopping the import', function () {
    $client = Tenant::factory()->create();
    $rows = implode("\n", [
        ',+51911111111,,,,,,,',
        'Sin contacto,,,,,,,,',
        'Fijo,014445555,,,,,,,',
        'Bien,+51944444444,,,,,,,',
        'Futuro,+51955555555,,,sms,2099-01-01,web,Acepto,',
    ])."\n";

    $import = BulkImport::query()->findOrFail(uploadBulk($client, $rows)->json('data.id'));

    expect($import->invalid)->toBe(3)->and($import->created)->toBe(2)
        ->and(collect($import->errors)->pluck('line')->all())->toContain(2, 3, 4, 6);
});

it('keeps the evidence of who uploaded what and why', function () {
    $client = Tenant::factory()->create();

    $import = BulkImport::query()->findOrFail(uploadBulk($client, "Ana,+51911111111,,,,,,,\n")->json('data.id'));

    expect($import->attestation_text)->toBe(BulkImport::ATTESTATION)
        ->and($import->declared_source)->toContain('Formulario de suscripción')
        ->and(Storage::disk('local')->exists($import->path))->toBeTrue()
        ->and(AuditLog::query()->where('tenant_id', $client->id)->pluck('action')->all())
        ->toContain('bulk_import.uploaded', 'bulk_import.completed');
});

it('requires the attestation and a meaningful source', function () {
    $client = Tenant::factory()->create();

    uploadBulk($client, "Ana,+51911111111,,,,,,,\n", ['attestation' => '0', 'declared_source' => 'base'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attestation', 'declared_source']);
});

it('cannot target the platform itself', function () {
    $owner = platformOwner();

    uploadBulk($owner->tenant, "Ana,+51911111111,,,,,,,\n")->assertUnprocessable()->assertJsonValidationErrors('client_id');
});

it('runs on the queue', function () {
    Queue::fake();
    $client = Tenant::factory()->create();

    $id = uploadBulk($client, "Ana,+51911111111,,,,,,,\n")->assertStatus(202)->assertJsonPath('data.status', 'queued')->json('data.id');

    Queue::assertPushed(ImportBulkContacts::class, fn ($job) => $job->bulkImportId === $id);
});

it('lists imports and shows row errors in the detail', function () {
    $client = Tenant::factory()->create(['name' => 'Cliente Base']);
    $id = uploadBulk($client, ",+51911111111,,,,,,,\n")->json('data.id');

    $this->actingAs(platformOwner())->getJson('/api/admin/bulk-imports')
        ->assertOk()
        ->assertJsonPath('data.0.tenant.name', 'Cliente Base')
        ->assertJsonMissingPath('data.0.errors');

    $this->actingAs(platformOwner())->getJson("/api/admin/bulk-imports/{$id}")
        ->assertOk()
        ->assertJsonPath('data.errors.0.message', 'Falta el nombre.');
});

it('keeps clients out of bulk imports', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/admin/bulk-imports')->assertForbidden();
});
