<?php

declare(strict_types=1);

use App\Models\AddOn;
use App\Models\AddOnRequest;
use App\Models\Alert;
use App\Models\Scopes\TenantScope;
use App\Models\TenantAddOn;
use App\Models\User;
use App\Services\AddOns\AddOnManager;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use Carbon\CarbonImmutable;

function addOnRequester(string $plan = 'basico'): User
{
    $user = User::factory()->create();
    app(MembershipManager::class)->startFromCatalog($user->tenant, $plan, CarbonImmutable::today());
    $user->tenant->refresh();
    app(TenantModules::class)->sync($user->tenant, app(TenantModules::class)->defaultsFor($plan));

    return $user;
}

function requestedAddOn($test, User $user, string $key = 'branding'): AddOnRequest
{
    $addOn = AddOn::query()->where('key', $key)->firstOrFail();
    $test->actingAs($user)->postJson("/api/add-ons/{$addOn->id}/request", ['comment' => 'Lo necesitamos'])->assertCreated();

    return AddOnRequest::query()->forTenant($user->tenant_id)->latest('id')->firstOrFail();
}

it('lists pending requests of every client for the platform', function () {
    $first = addOnRequester();
    $second = addOnRequester();
    requestedAddOn($this, $first);
    requestedAddOn($this, $second, 'reports');

    $data = $this->actingAs(platformOwner())->getJson('/api/admin/add-on-requests')->assertOk()->json('data');

    expect($data)->toHaveCount(2)
        ->and(collect($data)->pluck('add_on.name')->all())->toEqualCanonicalizing(['Marca en el login', 'Reportes'])
        ->and($data[0]['comment'])->toBe('Lo necesitamos')
        ->and($this->actingAs(platformOwner())->getJson("/api/admin/add-on-requests?client_id={$first->tenant_id}")->json('data'))->toHaveCount(1);
});

it('contracts the service when the platform approves the request', function () {
    $user = addOnRequester();
    $request = requestedAddOn($this, $user);

    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/approve", ['note' => 'Bienvenidos'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decision_note', 'Bienvenidos');

    $contract = TenantAddOn::query()->forTenant($user->tenant_id)->firstOrFail();
    expect($request->fresh()->tenant_add_on_id)->toBe($contract->id)
        ->and(app(TenantModules::class)->enabledFor($user->tenant_id))->toContain('branding')
        ->and(Alert::query()->withoutGlobalScope(TenantScope::class)->where('type', 'add_on.requested')->whereNull('resolved_at')->exists())->toBeFalse();

    expect(collect($this->actingAs($user)->getJson('/api/add-ons')->json('data'))->firstWhere('key', 'branding')['state'])->toBe('contracted');
});

it('requires a reason to reject and shows it to the client', function () {
    $user = addOnRequester();
    $request = requestedAddOn($this, $user);

    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/reject")->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/reject", ['note' => 'Falta firmar el contrato'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    $item = collect($this->actingAs($user)->getJson('/api/add-ons')->json('data'))->firstWhere('key', 'branding');
    expect($item['state'])->toBe('available')
        ->and($item['last_decision'])->toMatchArray(['status' => 'rejected', 'decision_note' => 'Falta firmar el contrato'])
        ->and(TenantAddOn::query()->forTenant($user->tenant_id)->exists())->toBeFalse();
});

it('does not decide a request twice', function () {
    $user = addOnRequester();
    $request = requestedAddOn($this, $user);
    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/approve")->assertOk();

    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/approve")->assertUnprocessable();
    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/reject", ['note' => 'x'])->assertUnprocessable();
});

it('refuses to approve when the service stopped being available', function () {
    $user = addOnRequester();
    $request = requestedAddOn($this, $user);
    AddOn::query()->where('key', 'branding')->update(['is_active' => false]);

    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/approve")
        ->assertUnprocessable()->assertJsonValidationErrors('add_on');

    expect($request->fresh()->status->value)->toBe('pending');
});

it('lets the platform contract and cancel services from the client record', function () {
    $user = addOnRequester();
    $branding = AddOn::query()->where('key', 'branding')->firstOrFail();

    $contract = $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$user->tenant_id}/add-ons", ['add_on_id' => $branding->id])
        ->assertCreated()
        ->assertJsonPath('data.monthly_price', '60.00')
        ->json('data');

    $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$user->tenant_id}/add-ons/{$contract['id']}/cancel")
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$user->tenant_id}/add-ons/{$contract['id']}/cancel", ['reason' => 'Pidió darlo de baja'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_reason', 'Pidió darlo de baja');

    $history = $this->actingAs(platformOwner())->getJson("/api/admin/clients/{$user->tenant_id}/add-ons")->assertOk()->json('data');
    expect($history)->toHaveCount(1)
        ->and(app(TenantModules::class)->enabledFor($user->tenant_id))->not->toContain('branding');
});

it('does not cancel a contract through another client', function () {
    $owner = addOnRequester();
    $other = addOnRequester();
    $contract = app(AddOnManager::class)->contract($owner->tenant, AddOn::query()->where('key', 'branding')->firstOrFail());

    $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$other->tenant_id}/add-ons/{$contract->id}/cancel", ['reason' => 'x'])
        ->assertNotFound();
});

it('lists the clients interested in an upcoming service', function () {
    $user = addOnRequester();
    $ai = AddOn::query()->where('key', 'ai-assistant')->firstOrFail();
    $this->actingAs($user)->postJson("/api/add-ons/{$ai->id}/interest")->assertNoContent();

    $this->actingAs(platformOwner())->getJson("/api/admin/add-ons/{$ai->id}/interests")
        ->assertOk()
        ->assertJsonPath('data.0.tenant.id', $user->tenant_id)
        ->assertJsonPath('data.0.user', $user->name);
});

it('keeps clients out of request decisions and contracts', function () {
    $user = addOnRequester();
    $request = requestedAddOn($this, $user);

    $this->actingAs($user)->postJson("/api/admin/add-on-requests/{$request->id}/approve")->assertForbidden();
    $this->actingAs($user)->getJson("/api/admin/clients/{$user->tenant_id}/add-ons")->assertForbidden();
});
