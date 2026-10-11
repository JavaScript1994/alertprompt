<?php

declare(strict_types=1);

use App\Jobs\DispatchCampaign;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Modules\TenantModules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function clientWithModules(array $modules): User
{
    $tenant = Tenant::factory()->create();
    app(TenantModules::class)->sync($tenant, $modules);

    return User::factory()->for($tenant)->create();
}

it('gives a new client the modules of its plan', function () {
    Notification::fake();

    $response = $this->actingAs(platformOwner())->postJson('/api/admin/clients', [
        'type' => 'company', 'name' => 'Starter SAC', 'document_type' => 'ruc', 'document_number' => '20131312955',
        'plan' => 'basico', 'admin_first_name' => 'Ana', 'admin_last_name' => 'Ríos', 'admin_email' => 'ana@basico.pe',
    ])->assertCreated();

    expect($response->json('data.modules'))->toEqualCanonicalizing(['sms', 'email', 'csv_import']);
});

it('exposes the active modules on /api/user', function () {
    $user = clientWithModules(['sms', 'email']);

    $this->actingAs($user)->getJson('/api/user')->assertJsonPath('data.tenant.modules', ['email', 'sms']);
});

it('gives the platform every module', function () {
    $modules = $this->actingAs(platformOwner())->getJson('/api/user')->json('data.tenant.modules');

    expect($modules)->toEqualCanonicalizing(array_keys(config('modules.catalog')));
});

it('blocks templates on a channel the client has not contracted', function () {
    $user = clientWithModules(['sms']);

    $this->actingAs($user)->postJson('/api/templates', [
        'channel' => 'whatsapp', 'category' => 'utility', 'name' => 'X', 'body' => 'Hola',
    ])->assertUnprocessable()->assertJsonValidationErrors('channel');

    $this->actingAs($user)->postJson('/api/templates', [
        'channel' => 'sms', 'category' => 'utility', 'name' => 'Y', 'body' => 'Hola',
    ])->assertCreated();
});

it('blocks scheduling without the scheduling module', function () {
    $user = clientWithModules(['sms']);
    $template = Template::factory()->for($user->tenant)->create(['channel' => 'sms', 'status' => 'approved']);
    $contact = Contact::factory()->for($user->tenant)->create();

    $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Agendada', 'template_id' => $template->id, 'contact_ids' => [$contact->id],
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
});

it('refuses to dispatch a campaign whose channel was disabled afterwards', function () {
    Queue::fake();
    $user = clientWithModules(['email']);
    $campaign = Campaign::factory()->for($user->tenant)->create(['channel' => 'sms', 'status' => 'draft']);

    $this->actingAs($user)->postJson("/api/campaigns/{$campaign->id}/dispatch")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Tu plan no incluye el canal SMS.');
    Queue::assertNothingPushed();
});

it('requires the csv_import module to import contacts', function () {
    $user = clientWithModules(['sms']);
    $csv = UploadedFile::fake()->createWithContent('c.csv', "name,phone\nAna,+51999111222\n");

    $this->actingAs($user)->postJson('/api/contacts/import', ['file' => $csv])->assertForbidden();
});

it('lets the owner change the modules of a client with audit', function () {
    $client = Tenant::factory()->create();
    app(TenantModules::class)->sync($client, ['sms']);

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$client->id}/modules", ['modules' => ['sms', 'whatsapp']])
        ->assertOk()
        ->assertJsonPath('data', ['sms', 'whatsapp']);

    $log = AuditLog::query()->where('tenant_id', $client->id)->where('action', 'client.modules_changed')->latest('id')->firstOrFail();
    expect($log->metadata)->toBe(['enabled' => ['whatsapp'], 'disabled' => []]);
});

it('rejects unknown modules', function () {
    $client = Tenant::factory()->create();

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$client->id}/modules", ['modules' => ['teleport']])
        ->assertUnprocessable();
});

it('summarises module usage for the catalog', function () {
    $owner = platformOwner();
    foreach ([['sms'], ['sms', 'email']] as $modules) {
        app(TenantModules::class)->sync(Tenant::factory()->create(), $modules);
    }

    $data = collect($this->actingAs($owner)->getJson('/api/admin/modules')->assertOk()->json('data'))->keyBy('key');

    expect($data['sms']['clients_count'])->toBe(2)
        ->and($data['email']['clients_count'])->toBe(1)
        ->and($data['whatsapp']['included_in_plans'])->not->toContain('Básico');
});

it('keeps scheduled campaigns waiting when the client lost the channel module', function () {
    Queue::fake();
    $user = clientWithModules(['email', 'scheduling']);
    Campaign::factory()->for($user->tenant)->create([
        'channel' => 'sms', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute(),
    ]);
    $ok = Campaign::factory()->for($user->tenant)->create([
        'channel' => 'email', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('campaigns:dispatch-scheduled')->assertSuccessful();

    Queue::assertPushed(DispatchCampaign::class, 1);
    Queue::assertPushed(DispatchCampaign::class, fn ($job) => $job->campaignId === $ok->id);
});
