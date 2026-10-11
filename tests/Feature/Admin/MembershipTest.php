<?php

declare(strict_types=1);

use App\Enums\CampaignRecipientStatus;
use App\Enums\Channel;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Memberships\MembershipManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function membershipPayload(array $overrides = []): array
{
    return [
        'plan' => 'intermedio',
        'billing_cycle' => 'monthly',
        'price' => '450.00',
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
        'quotas' => ['whatsapp' => 1000, 'sms' => 500, 'email' => null],
        'contract_reference' => 'CTR-2026-001',
        ...$overrides,
    ];
}

function membershipsOf(Tenant $tenant): Builder
{
    return Membership::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id);
}

it('activates a membership that starts today and moves the client to its plan', function () {
    $client = Tenant::factory()->create(['plan' => 'basico']);

    $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$client->id}/memberships", membershipPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.quotas.whatsapp', 1000)
        ->assertJsonPath('data.quotas.email', null);

    expect($client->fresh()->plan)->toBe('intermedio');
});

it('schedules a future membership and activates it when it starts, expiring the previous one', function () {
    $client = Tenant::factory()->create();
    $owner = platformOwner();
    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/memberships", membershipPayload(['plan' => 'basico']))->assertCreated();

    $next = $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/memberships", membershipPayload([
        'plan' => 'avanzado',
        'starts_at' => now()->addMonth()->addDay()->toDateString(),
        'ends_at' => now()->addMonths(13)->toDateString(),
        'billing_cycle' => 'yearly',
    ]))->assertCreated()->assertJsonPath('data.status', 'scheduled');

    app(MembershipManager::class)->refresh(CarbonImmutable::today()->addMonth()->addDay());

    expect(membershipsOf($client)->find($next->json('data.id'))->status)->toBe(MembershipStatus::Active)
        ->and(membershipsOf($client)->where('status', MembershipStatus::Active)->count())->toBe(1)
        ->and($client->fresh()->plan)->toBe('avanzado');
});

it('renews an expired membership with the same terms so the client is never left without one', function () {
    $client = Tenant::factory()->create();
    $old = app(MembershipManager::class)->create($client, membershipPayload([
        'starts_at' => now()->subMonths(2)->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
    ]));

    $this->artisan('memberships:refresh')->assertSuccessful();

    $renewal = membershipsOf($client)->where('status', MembershipStatus::Active)->sole();
    expect($old->fresh()->status)->toBe(MembershipStatus::Expired)
        ->and($renewal->plan)->toBe($old->plan)
        ->and((string) $renewal->price)->toBe((string) $old->price)
        ->and($renewal->starts_at->toDateString())->toBe(now()->toDateString())
        ->and(AuditLog::query()->where('action', 'membership.renewed')->exists())->toBeTrue();
});

it('does not renew when a successor membership is already scheduled', function () {
    $client = Tenant::factory()->create();
    $manager = app(MembershipManager::class);
    $manager->create($client, membershipPayload(['starts_at' => now()->subMonth()->toDateString(), 'ends_at' => now()->subDay()->toDateString()]));
    $manager->create($client, membershipPayload(['plan' => 'avanzado', 'starts_at' => now()->addDays(2)->toDateString(), 'ends_at' => now()->addYear()->toDateString()]));

    $manager->refresh();

    expect(membershipsOf($client)->count())->toBe(2);
});

it('rejects overlapping scheduled memberships', function () {
    $client = Tenant::factory()->create();
    $owner = platformOwner();
    $future = ['starts_at' => now()->addMonth()->toDateString(), 'ends_at' => now()->addMonths(2)->toDateString()];
    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/memberships", membershipPayload($future))->assertCreated();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/memberships", membershipPayload($future))
        ->assertUnprocessable()->assertJsonValidationErrors('starts_at');
});

it('cancels only scheduled memberships, never the current one', function () {
    $client = Tenant::factory()->create();
    $current = app(MembershipManager::class)->create($client, membershipPayload());
    $next = app(MembershipManager::class)->create($client, membershipPayload([
        'starts_at' => now()->addMonths(2)->toDateString(), 'ends_at' => now()->addYear()->toDateString(),
    ]));
    $owner = platformOwner();

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/memberships/{$current->id}/cancel", ['reason' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('membership');

    $this->actingAs($owner)->postJson("/api/admin/clients/{$client->id}/memberships/{$next->id}/cancel", ['reason' => 'Ya no la quiere'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_reason', 'Ya no la quiere');
});

it('gives every new client the membership of its plan', function () {
    Notification::fake();

    $id = $this->actingAs(platformOwner())->postJson('/api/admin/clients', [
        'type' => 'company', 'name' => 'Nueva SAC', 'document_type' => 'ruc', 'document_number' => '20131312955',
        'plan' => 'avanzado', 'admin_first_name' => 'Ana', 'admin_last_name' => 'Ríos', 'admin_email' => 'ana@nueva.pe',
    ])->assertCreated()->json('data.id');

    $membership = membershipsOf(Tenant::query()->findOrFail($id))->sole();
    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->plan)->toBe('avanzado')
        ->and((string) $membership->price)->toBe('1200.00')
        ->and($membership->quotas['whatsapp'])->toBe(20000);
});

it('shows the client its membership and monthly usage, without internal notes', function () {
    $user = User::factory()->create();
    app(MembershipManager::class)->create($user->tenant, membershipPayload(['notes' => 'Descuento por pronto pago']));
    $campaign = Campaign::factory()->for($user->tenant)->create(['channel' => Channel::Sms]);
    CampaignRecipient::factory()->count(3)->create([
        'campaign_id' => $campaign->id, 'contact_id' => Contact::factory()->for($user->tenant), 'status' => CampaignRecipientStatus::Delivered, 'sent_at' => now(),
    ]);

    $data = $this->actingAs($user)->getJson('/api/membership')->assertOk()->json('data');

    expect($data['current']['plan'])->toBe('intermedio')
        ->and($data['current'])->not->toHaveKey('notes')
        ->and($data['usage']['sms'])->toBe(['used' => 3, 'quota' => 500])
        ->and($data['usage']['email']['quota'])->toBeNull();
});

it('blocks a dispatch over the quota only when quotas are enforced', function () {
    Queue::fake();
    $user = User::factory()->create();
    app(MembershipManager::class)->create($user->tenant, membershipPayload(['quotas' => ['sms' => 2]]));
    $campaign = Campaign::factory()->for($user->tenant)->create(['channel' => Channel::Sms, 'status' => 'draft']);
    CampaignRecipient::factory()->count(3)->create([
        'campaign_id' => $campaign->id, 'contact_id' => Contact::factory()->for($user->tenant), 'status' => CampaignRecipientStatus::Pending,
    ]);

    config(['memberships.enforce_quotas' => true]);
    $this->actingAs($user)->postJson("/api/campaigns/{$campaign->id}/dispatch")
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'cuota mensual de SMS'));

    config(['memberships.enforce_quotas' => false]);
    $this->actingAs($user)->postJson("/api/campaigns/{$campaign->id}/dispatch")->assertOk();
});

it('keeps memberships management in the platform', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson("/api/admin/clients/{$user->tenant_id}/memberships", membershipPayload())->assertForbidden();
});
