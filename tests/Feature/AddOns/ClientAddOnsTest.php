<?php

declare(strict_types=1);

use App\Models\AddOn;
use App\Models\AddOnInterest;
use App\Models\AddOnRequest;
use App\Models\Alert;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\AddOns\AddOnManager;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

function addOnsClientUser(string $plan = 'basico', ?string $role = null): User
{
    $user = $role ? User::factory()->withRole($role)->create() : User::factory()->create();
    app(MembershipManager::class)->startFromCatalog($user->tenant, $plan, CarbonImmutable::today());
    $user->tenant->refresh();
    app(TenantModules::class)->sync($user->tenant, app(TenantModules::class)->defaultsFor($plan));

    return $user;
}

function catalogItem(string $key): AddOn
{
    return AddOn::query()->where('key', $key)->firstOrFail();
}

function clientCatalog($test, User $user): Collection
{
    return collect($test->actingAs($user)->getJson('/api/add-ons')->assertOk()->json('data'))->keyBy('key');
}

it('shows each service with its state for the client', function () {
    $user = addOnsClientUser('intermedio');
    app(AddOnManager::class)->contract($user->tenant, catalogItem('branding'));
    catalogItem('attendance')->update(['is_active' => false]);

    $data = clientCatalog($this, $user);

    expect($data['branding']['state'])->toBe('contracted')
        ->and($data['branding']['contract']['monthly_price'])->toBe('60.00')
        ->and($data['reports']['state'])->toBe('included')
        ->and($data['ai-assistant']['state'])->toBe('coming_soon')
        ->and($data['ai-assistant']['interested'])->toBeFalse()
        ->and($data->has('attendance'))->toBeFalse();
});

it('lets the client request an available service and alerts the platform', function () {
    $user = addOnsClientUser();
    $branding = catalogItem('branding');

    $this->actingAs($user)->postJson("/api/add-ons/{$branding->id}/request", ['comment' => 'Para el lanzamiento'])->assertCreated();

    $item = clientCatalog($this, $user)['branding'];
    expect($item['state'])->toBe('pending')
        ->and($item['pending_request']['comment'])->toBe('Para el lanzamiento')
        ->and($item['pending_request']['requested_by'])->toBe($user->name)
        ->and(Alert::query()->withoutGlobalScope(TenantScope::class)->where('type', 'add_on.requested')->whereNull('resolved_at')->count())->toBe(1);
});

it('rejects a second request, an active service or one that is coming soon', function () {
    $user = addOnsClientUser('intermedio');
    $branding = catalogItem('branding');

    $this->actingAs($user)->postJson("/api/add-ons/{$branding->id}/request")->assertCreated();
    $this->actingAs($user)->postJson("/api/add-ons/{$branding->id}/request")->assertUnprocessable()->assertJsonValidationErrors('add_on');
    $this->actingAs($user)->postJson('/api/add-ons/'.catalogItem('reports')->id.'/request')->assertUnprocessable();
    $this->actingAs($user)->postJson('/api/add-ons/'.catalogItem('ai-assistant')->id.'/request')->assertUnprocessable();
});

it('lets the client cancel a pending request and resolves the alert', function () {
    $user = addOnsClientUser();
    $branding = catalogItem('branding');
    $this->actingAs($user)->postJson("/api/add-ons/{$branding->id}/request")->assertCreated();

    $this->actingAs($user)->deleteJson("/api/add-ons/{$branding->id}/request")->assertNoContent();
    $this->actingAs($user)->deleteJson("/api/add-ons/{$branding->id}/request")->assertNotFound();

    expect(AddOnRequest::query()->forTenant($user->tenant_id)->first()->status->value)->toBe('cancelled')
        ->and(clientCatalog($this, $user)['branding']['state'])->toBe('available')
        ->and(Alert::query()->withoutGlobalScope(TenantScope::class)->where('type', 'add_on.requested')->whereNull('resolved_at')->exists())->toBeFalse();
});

it('records "Me interesa" once and lets the client undo it', function () {
    $user = addOnsClientUser();
    $ai = catalogItem('ai-assistant');

    $this->actingAs($user)->postJson("/api/add-ons/{$ai->id}/interest")->assertNoContent();
    $this->actingAs($user)->postJson("/api/add-ons/{$ai->id}/interest")->assertNoContent();

    expect(AddOnInterest::query()->forTenant($user->tenant_id)->count())->toBe(1)
        ->and(Alert::query()->withoutGlobalScope(TenantScope::class)->where('type', 'add_on.interested')->value('occurrences'))->toBe(1)
        ->and(clientCatalog($this, $user)['ai-assistant']['interested'])->toBeTrue();

    $this->actingAs($user)->deleteJson("/api/add-ons/{$ai->id}/interest")->assertNoContent();

    expect(clientCatalog($this, $user)['ai-assistant']['interested'])->toBeFalse();
});

it('only accepts "Me interesa" on services that are coming soon', function () {
    $user = addOnsClientUser();

    $this->actingAs($user)->postJson('/api/add-ons/'.catalogItem('branding')->id.'/interest')->assertUnprocessable();
});

it('keeps each client requests and interests apart', function () {
    $first = addOnsClientUser();
    $second = addOnsClientUser();
    $this->actingAs($first)->postJson('/api/add-ons/'.catalogItem('branding')->id.'/request')->assertCreated();
    $this->actingAs($first)->postJson('/api/add-ons/'.catalogItem('ai-assistant')->id.'/interest')->assertNoContent();

    $data = clientCatalog($this, $second);

    expect($data['branding']['state'])->toBe('available')
        ->and($data['ai-assistant']['interested'])->toBeFalse();
});

it('requires the add-on permissions', function () {
    $user = addOnsClientUser(role: 'client-user');

    $this->actingAs($user)->getJson('/api/add-ons')->assertForbidden();
    $this->actingAs($user)->postJson('/api/add-ons/'.catalogItem('branding')->id.'/request')->assertForbidden();
});

it('does not let support mode request services on behalf of the client', function () {
    $user = addOnsClientUser();
    $owner = platformOwner();
    $this->actingAs($owner)->postJson("/api/admin/clients/{$user->tenant_id}/impersonate")->assertNoContent();

    $this->getJson('/api/add-ons')->assertOk();
    $this->postJson('/api/add-ons/'.catalogItem('branding')->id.'/request')->assertForbidden();
});
