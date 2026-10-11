<?php

declare(strict_types=1);

use App\Enums\TenantAddOnStatus;
use App\Models\AddOn;
use App\Models\AddOnInterest;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantAddOn;
use App\Models\User;
use App\Services\AddOns\AddOnManager;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function addOnClient(string $plan = 'basico'): Tenant
{
    $user = User::factory()->create();
    app(MembershipManager::class)->startFromCatalog($user->tenant, $plan, CarbonImmutable::today());
    $tenant = $user->tenant->refresh();
    app(TenantModules::class)->sync($tenant, app(TenantModules::class)->defaultsFor($plan));

    return $tenant;
}

function addOn(string $key): AddOn
{
    return AddOn::query()->where('key', $key)->firstOrFail();
}

function modulesOf(Tenant $tenant): array
{
    return app(TenantModules::class)->enabledFor($tenant->id);
}

it('ships a sample catalog with available and coming soon services', function () {
    expect(AddOn::query()->where('status', 'available')->pluck('module')->all())->toEqualCanonicalizing(['scheduling', 'reports', 'branding'])
        ->and(AddOn::query()->where('status', 'coming_soon')->pluck('key')->all())->toEqualCanonicalizing(['ai-assistant', 'whatsapp-live-chat', 'attendance'])
        ->and(AddOn::query()->where('status', 'coming_soon')->whereNotNull('module')->exists())->toBeFalse();
});

it('turns on the module and copies the price when a service is contracted', function () {
    $tenant = addOnClient();

    $contract = app(AddOnManager::class)->contract($tenant, addOn('branding'), platformOwner());
    AddOn::query()->where('key', 'branding')->update(['monthly_price' => 999]);

    expect(modulesOf($tenant))->toContain('branding')
        ->and((string) $contract->fresh()->monthly_price)->toBe('60.00')
        ->and($contract->status)->toBe(TenantAddOnStatus::Active)
        ->and(AuditLog::query()->where('action', 'add_on.contracted')->exists())->toBeTrue();
});

it('refuses services that are coming soon, inactive, already active or included in the plan', function (string $key, string $plan, ?Closure $setup) {
    $tenant = addOnClient($plan);
    $setup?->call(test(), $tenant);

    expect(fn () => app(AddOnManager::class)->contract($tenant, addOn($key)))->toThrow(ValidationException::class);
})->with([
    'próximamente' => ['ai-assistant', 'basico', null],
    'inactivo' => ['branding', 'basico', fn () => AddOn::query()->where('key', 'branding')->update(['is_active' => false])],
    'incluido en el plan' => ['reports', 'intermedio', null],
    'ya contratado' => ['branding', 'basico', fn (Tenant $t) => app(AddOnManager::class)->contract($t, addOn('branding'))],
]);

it('refuses contracting for the platform', function () {
    expect(fn () => app(AddOnManager::class)->contract(platformOwner()->tenant, addOn('branding')))->toThrow(ValidationException::class);
});

it('turns the module off when the service is cancelled', function () {
    $tenant = addOnClient();
    $contract = app(AddOnManager::class)->contract($tenant, addOn('branding'));

    app(AddOnManager::class)->cancel($contract, platformOwner(), 'Ya no lo usa');

    expect(modulesOf($tenant))->not->toContain('branding')
        ->and($contract->fresh()->status)->toBe(TenantAddOnStatus::Cancelled)
        ->and($contract->fresh()->cancel_reason)->toBe('Ya no lo usa')
        ->and(fn () => app(AddOnManager::class)->cancel($contract->fresh()))->toThrow(ValidationException::class);
});

it('allows contracting again after cancelling', function () {
    $tenant = addOnClient();
    $first = app(AddOnManager::class)->contract($tenant, addOn('branding'));
    app(AddOnManager::class)->cancel($first);

    app(AddOnManager::class)->contract($tenant, addOn('branding'));

    expect(TenantAddOn::query()->forTenant($tenant->id)->count())->toBe(2)
        ->and(modulesOf($tenant))->toContain('branding');
});

it('keeps contracted modules on a plan change and cancels what the new plan includes', function () {
    $tenant = addOnClient('basico');
    $branding = app(AddOnManager::class)->contract($tenant, addOn('branding'));
    $reports = app(AddOnManager::class)->contract($tenant, addOn('reports'));

    app(MembershipManager::class)->startFromCatalog($tenant, 'intermedio', CarbonImmutable::today());

    expect(modulesOf($tenant))->toContain('branding', 'reports', 'scheduling')
        ->and($branding->fresh()->status)->toBe(TenantAddOnStatus::Active)
        ->and($reports->fresh()->status)->toBe(TenantAddOnStatus::Cancelled)
        ->and($reports->fresh()->cancel_reason)->toBe('Incluido en el plan nuevo.')
        ->and(AuditLog::query()->where('action', 'add_on.included_in_plan')->count())->toBe(1);
});

it('does not let the administrator switch off a contracted module by hand', function () {
    $tenant = addOnClient();
    app(AddOnManager::class)->contract($tenant, addOn('branding'));

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$tenant->id}/modules", ['modules' => ['sms', 'email']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('modules');

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$tenant->id}/modules", ['modules' => ['sms', 'branding']])
        ->assertOk();
});

it('lists the catalog with contracts and interested clients', function () {
    $tenant = addOnClient();
    app(AddOnManager::class)->contract($tenant, addOn('branding'));
    AddOnInterest::query()->create(['tenant_id' => $tenant->id, 'add_on_id' => addOn('attendance')->id]);

    $data = collect($this->actingAs(platformOwner())->getJson('/api/admin/add-ons')->assertOk()->json('data'))->keyBy('key');

    expect($data)->toHaveCount(6)
        ->and($data['branding']['active_contracts_count'])->toBe(1)
        ->and($data['branding']['monthly_price'])->toBe('60.00')
        ->and($data['attendance']['interests_count'])->toBe(1)
        ->and($data['attendance']['status'])->toBe('coming_soon');
});

it('creates services with audit and validates module and price', function () {
    $owner = platformOwner();

    $this->actingAs($owner)->postJson('/api/admin/add-ons', ['name' => 'Firma digital', 'status' => 'coming_soon'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'firma-digital')
        ->assertJsonPath('data.module', null);

    $this->actingAs($owner)->postJson('/api/admin/add-ons', ['name' => 'WhatsApp', 'status' => 'available'])
        ->assertUnprocessable()->assertJsonValidationErrors(['module', 'monthly_price']);
    $this->actingAs($owner)->postJson('/api/admin/add-ons', ['name' => 'Otra marca', 'status' => 'available', 'module' => 'branding', 'monthly_price' => 10])
        ->assertUnprocessable()->assertJsonValidationErrors('module');
    $this->actingAs($owner)->postJson('/api/admin/add-ons', ['name' => 'Raro', 'status' => 'coming_soon', 'module' => 'whatsapp'])
        ->assertUnprocessable()->assertJsonValidationErrors('module');

    $this->actingAs($owner)->postJson('/api/admin/add-ons', ['name' => 'Canal WhatsApp', 'status' => 'available', 'module' => 'whatsapp', 'monthly_price' => 120])
        ->assertCreated()->assertJsonPath('data.monthly_price', '120.00');

    expect(AuditLog::query()->where('action', 'add_on.created')->count())->toBe(2);
});

it('edits price freely but not module or status while clients have it', function () {
    $tenant = addOnClient();
    $branding = addOn('branding');
    app(AddOnManager::class)->contract($tenant, $branding);
    $payload = ['name' => 'Tu marca', 'status' => 'available', 'module' => 'branding', 'monthly_price' => 75];

    $this->actingAs(platformOwner())->putJson("/api/admin/add-ons/{$branding->id}", $payload)
        ->assertOk()->assertJsonPath('data.name', 'Tu marca')->assertJsonPath('data.monthly_price', '75.00');

    $this->actingAs(platformOwner())->putJson("/api/admin/add-ons/{$branding->id}", [...$payload, 'status' => 'coming_soon', 'module' => null, 'monthly_price' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('module');
});

it('deactivates a service without cancelling existing contracts', function () {
    $tenant = addOnClient();
    $branding = addOn('branding');
    $contract = app(AddOnManager::class)->contract($tenant, $branding);

    $this->actingAs(platformOwner())->postJson("/api/admin/add-ons/{$branding->id}/deactivate")
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect($contract->fresh()->status)->toBe(TenantAddOnStatus::Active)
        ->and(modulesOf($tenant))->toContain('branding');

    $this->actingAs(platformOwner())->postJson("/api/admin/add-ons/{$branding->id}/activate")->assertJsonPath('data.is_active', true);
});

it('keeps clients out of the add-on catalog administration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin/add-ons')->assertForbidden();
    $this->actingAs($user)->postJson('/api/admin/add-ons', ['name' => 'X', 'status' => 'coming_soon'])->assertForbidden();
});
