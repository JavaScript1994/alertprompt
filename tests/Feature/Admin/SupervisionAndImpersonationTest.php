<?php

declare(strict_types=1);

use App\Enums\RoleScope;
use App\Jobs\ImportContactsCsv;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('lets the platform read a client contacts without mixing tenants', function () {
    $owner = platformOwner();
    $client = Tenant::factory()->create();
    Contact::factory()->for($client)->count(2)->create();
    Contact::factory()->for(Tenant::factory()->create())->count(5)->create();

    $response = $this->actingAs($owner)->getJson("/api/admin/clients/{$client->id}/supervision/contacts");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('does not allow writing through supervision routes', function () {
    $client = Tenant::factory()->create();

    $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$client->id}/supervision/contacts", [
        'name' => 'X', 'phone' => '+51999999999',
    ])->assertMethodNotAllowed();
});

it('requires the supervision permission', function () {
    $role = Role::query()->create([
        'tenant_id' => null, 'name' => 'comercial', 'guard_name' => 'web',
        'label' => 'Comercial', 'scope' => RoleScope::Platform,
    ]);
    $role->givePermissionTo('admin.clients.view');
    $platform = Tenant::platform() ?? Tenant::factory()->platform()->create();
    $sales = User::factory()->for($platform)->withRole('comercial')->create();
    $client = Tenant::factory()->create();

    $this->actingAs($sales)->getJson("/api/admin/clients/{$client->id}")->assertOk();
    $this->actingAs($sales)->getJson("/api/admin/clients/{$client->id}/supervision/contacts")->assertForbidden();
    $this->actingAs($sales)->postJson("/api/admin/clients/{$client->id}/impersonate")->assertForbidden();
});

it('enters a client panel, works on its data with audit, and leaves', function () {
    $owner = platformOwner();
    $client = Tenant::factory()->create(['name' => 'Cliente Soporte']);
    Contact::factory()->for($client)->count(2)->create();
    Contact::factory()->for($owner->tenant)->create();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/impersonate")->assertNoContent();

    $this->getJson('/api/user')
        ->assertJsonPath('data.impersonating.name', 'Cliente Soporte')
        ->assertJsonPath('data.tenant.is_platform', true);

    expect($this->getJson('/api/contacts')->json('data'))->toHaveCount(2);

    $created = $this->postJson('/api/contacts', ['name' => 'Nuevo', 'phone' => '+51988777666'])->assertCreated();
    expect(Contact::query()->forTenant($client->id)->whereKey($created->json('data.id'))->exists())->toBeTrue();

    $this->deleteJson('/api/admin/impersonation')->assertNoContent();

    $this->getJson('/api/user')->assertJsonPath('data.impersonating', null);
    // Fuera del modo soporte, la administración general no tiene panel de mensajería.
    $this->getJson('/api/contacts')->assertForbidden();

    expect(AuditLog::query()->where('tenant_id', $client->id)->pluck('action')->all())
        ->toContain('impersonation.started', 'impersonation.request', 'impersonation.stopped');
});

it('never dispatches campaigns on behalf of a client', function () {
    $owner = platformOwner();
    $client = Tenant::factory()->create();
    $campaign = Campaign::factory()->for($client)->create();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/impersonate")->assertNoContent();

    $this->postJson("/api/campaigns/{$campaign->id}/dispatch")->assertForbidden();
});

it('ignores a stale impersonation session when the permission is gone', function () {
    // Sin permiso de soporte la sesión no cuenta: no ve datos del cliente y,
    // como plataforma, tampoco tiene panel propio.
    $platform = Tenant::platform() ?? Tenant::factory()->platform()->create();
    $operator = User::factory()->for($platform)->withRole('client-user')->create();
    $client = Tenant::factory()->create();
    Contact::factory()->for($client)->count(3)->create();

    $this->actingAs($operator)
        ->withSession([Impersonation::SESSION_KEY => $client->id])
        ->getJson('/api/contacts')
        ->assertForbidden();
});

it('does not let client users impersonate', function () {
    $other = Tenant::factory()->create();

    $this->actingAs(User::factory()->create())->postJson("/api/admin/clients/{$other->id}/impersonate")->assertForbidden();
});

it('writes templates, campaigns and imports into the client tenant in support mode', function () {
    Queue::fake();
    Storage::fake('local');
    $owner = platformOwner();
    $client = Tenant::factory()->create();
    $contact = Contact::factory()->for($client)->create();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/impersonate")->assertNoContent();

    $template = $this->postJson('/api/templates', [
        'channel' => 'sms', 'category' => 'utility', 'name' => 'Aviso', 'body' => 'Hola {{nombre}}',
    ])->assertCreated();
    expect(Template::query()->forTenant($client->id)->whereKey($template->json('data.id'))->exists())->toBeTrue();

    Template::query()->forTenant($client->id)->whereKey($template->json('data.id'))->update(['status' => 'approved']);
    $this->postJson('/api/campaigns', [
        'name' => 'Soporte', 'template_id' => $template->json('data.id'), 'contact_ids' => [$contact->id],
    ])->assertCreated();

    $csv = UploadedFile::fake()->createWithContent('c.csv', "name,phone\nAna,+51999111222\n");
    $this->postJson('/api/contacts/import', ['file' => $csv])->assertSuccessful();
    Queue::assertPushed(ImportContactsCsv::class, fn ($job) => $job->tenantId === $client->id);
});
