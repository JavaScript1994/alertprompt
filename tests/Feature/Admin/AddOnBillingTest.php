<?php

declare(strict_types=1);

use App\Models\AddOn;
use App\Models\AddOnRequest;
use App\Models\Invoice;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AddOns\AddOnManager;
use App\Services\Billing\InvoiceManager;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Cliente con plan Básico desde el 01/01/2026 (mensual a S/ 150 o anual a S/ 1500). */
function billedClient(string $cycle = 'monthly'): Tenant
{
    test()->travelTo(CarbonImmutable::parse('2026-01-01 09:00'));
    $tenant = Tenant::factory()->create();
    app(MembershipManager::class)->create($tenant, [
        'plan' => 'basico', 'billing_cycle' => $cycle, 'price' => $cycle === 'monthly' ? '150' : '1500',
        'starts_at' => '2026-01-01', 'ends_at' => '2027-12-31',
    ]);
    app(TenantModules::class)->sync($tenant->refresh(), app(TenantModules::class)->defaultsFor('basico'));

    return $tenant;
}

function contractOn(Tenant $tenant, string $key, string $date)
{
    test()->travelTo(CarbonImmutable::parse("{$date} 10:00"));

    return app(AddOnManager::class)->contract($tenant, AddOn::query()->where('key', $key)->firstOrFail());
}

/** @return Collection<int, Invoice> */
function billsOf(Tenant $tenant): Collection
{
    return Invoice::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->orderBy('id')->get();
}

function runBillingOn(string $date): void
{
    test()->travelTo(CarbonImmutable::parse("{$date} 06:00"));
    app(InvoiceManager::class)->runDaily();
}

it('adds a service to the monthly invoice from the next period', function () {
    $tenant = billedClient();
    $contract = contractOn($tenant, 'branding', '2026-01-15');

    expect(billsOf($tenant))->toHaveCount(1)
        ->and((string) billsOf($tenant)->first()->subtotal)->toBe('150.00');

    runBillingOn('2026-02-01');

    $february = billsOf($tenant)->last();
    expect(billsOf($tenant))->toHaveCount(2)
        ->and((string) $february->subtotal)->toBe('210.00')
        ->and((string) $february->total)->toBe('247.80')
        ->and($february->description)->toEndWith('+ Marca en el login')
        ->and($february->lines)->toEqual([
            ['description' => 'Plan Básico (mensual) del 01/02/2026 al 28/02/2026', 'amount' => '150.00'],
            ['description' => 'Servicio Marca en el login (mensual)', 'amount' => '60.00', 'tenant_add_on_id' => $contract->id],
        ]);
});

it('bills the price agreed in the contract, not the current catalog price', function () {
    $tenant = billedClient();
    contractOn($tenant, 'branding', '2026-01-15');
    AddOn::query()->where('key', 'branding')->update(['monthly_price' => 99]);

    runBillingOn('2026-02-01');

    expect((string) billsOf($tenant)->last()->subtotal)->toBe('210.00');
});

it('stops billing a service cancelled before the period starts', function () {
    $tenant = billedClient();
    $contract = contractOn($tenant, 'branding', '2026-01-15');
    test()->travelTo(CarbonImmutable::parse('2026-01-20'));
    app(AddOnManager::class)->cancel($contract);

    runBillingOn('2026-02-01');

    expect((string) billsOf($tenant)->last()->subtotal)->toBe('150.00');
});

it('bills the period in course when the service is cancelled after it started', function () {
    $tenant = billedClient();
    $contract = contractOn($tenant, 'branding', '2026-01-15');
    runBillingOn('2026-02-01');
    test()->travelTo(CarbonImmutable::parse('2026-02-10'));
    app(AddOnManager::class)->cancel($contract);

    runBillingOn('2026-03-01');

    expect(billsOf($tenant)->pluck('subtotal')->map(fn ($amount) => (string) $amount)->all())->toBe(['150.00', '210.00', '150.00']);
});

it('does not bill a service the new plan includes on a plan change', function () {
    $tenant = billedClient();
    contractOn($tenant, 'reports', '2026-01-10');
    contractOn($tenant, 'branding', '2026-01-10');

    test()->travelTo(CarbonImmutable::parse('2026-01-20 12:00'));
    app(MembershipManager::class)->startFromCatalog($tenant, 'intermedio', CarbonImmutable::today());

    $invoice = billsOf($tenant)->last();
    expect((string) $invoice->subtotal)->toBe('510.00')
        ->and(collect($invoice->lines)->pluck('description')->all())->toBe([
            'Plan Intermedio (mensual) del 20/01/2026 al 19/02/2026',
            'Servicio Marca en el login (mensual)',
        ]);
});

it('still bills the services of a client whose plan is free', function () {
    test()->travelTo(CarbonImmutable::parse('2026-01-01 09:00'));
    $tenant = Tenant::factory()->create();
    app(MembershipManager::class)->create($tenant, [
        'plan' => 'basico', 'billing_cycle' => 'monthly', 'price' => '0',
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
    ]);
    app(TenantModules::class)->sync($tenant->refresh(), app(TenantModules::class)->defaultsFor('basico'));
    contractOn($tenant, 'branding', '2026-01-15');

    runBillingOn('2026-02-01');

    expect(billsOf($tenant))->toHaveCount(1)
        ->and((string) billsOf($tenant)->first()->subtotal)->toBe('60.00')
        ->and(billsOf($tenant)->first()->lines)->toHaveCount(1);
});

it('prorates the rest of the year for a yearly membership and bills twelve months on renewal', function () {
    $tenant = billedClient('yearly');
    $contract = contractOn($tenant, 'branding', '2026-03-15');

    $proration = billsOf($tenant)->last();
    expect(billsOf($tenant))->toHaveCount(2)
        ->and($proration->tenant_add_on_id)->toBe($contract->id)
        ->and((string) $proration->subtotal)->toBe('540.00')
        ->and($proration->description)->toBe('Servicio Marca en el login: 9 meses del 01/04/2026 al 31/12/2026 (plan anual)');

    runBillingOn('2027-01-01');

    $renewal = billsOf($tenant)->last();
    expect(billsOf($tenant))->toHaveCount(3)
        ->and((string) $renewal->subtotal)->toBe('2220.00')
        ->and($renewal->lines[1])->toEqual(['description' => 'Servicio Marca en el login (anual)', 'amount' => '720.00', 'tenant_add_on_id' => $contract->id]);
});

it('issues the proration once and not in the last month of the year', function () {
    $tenant = billedClient('yearly');
    $contract = contractOn($tenant, 'branding', '2026-12-10');

    expect(billsOf($tenant))->toHaveCount(1)
        ->and(app(InvoiceManager::class)->issueAddOnProration($contract))->toBeNull();

    $other = contractOn($tenant, 'reports', '2026-06-01');
    expect(app(InvoiceManager::class)->issueAddOnProration($other))->toBeNull()
        ->and(billsOf($tenant)->where('tenant_add_on_id', $other->id))->toHaveCount(1);
});

it('does not prorate services of monthly memberships', function () {
    $tenant = billedClient();
    contractOn($tenant, 'branding', '2026-03-15');

    expect(billsOf($tenant)->whereNotNull('tenant_add_on_id'))->toHaveCount(0);
});

it('prorates when the platform approves a request from a yearly client', function () {
    $tenant = billedClient('yearly');
    $user = User::factory()->for($tenant)->create();
    $branding = AddOn::query()->where('key', 'branding')->firstOrFail();
    test()->travelTo(CarbonImmutable::parse('2026-07-20 10:00'));
    $this->actingAs($user)->postJson("/api/add-ons/{$branding->id}/request")->assertCreated();
    $request = AddOnRequest::query()->forTenant($tenant->id)->firstOrFail();

    $this->actingAs(platformOwner())->postJson("/api/admin/add-on-requests/{$request->id}/approve")->assertOk();

    expect((string) billsOf($tenant)->last()->subtotal)->toBe('300.00');
});

it('shows the invoice lines to the client', function () {
    $tenant = billedClient();
    contractOn($tenant, 'branding', '2026-01-15');
    runBillingOn('2026-02-01');
    $user = User::factory()->for($tenant)->create();

    $this->actingAs($user)->getJson('/api/billing/invoices/'.billsOf($tenant)->last()->id)
        ->assertOk()
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.1.amount', '60.00');

    $this->actingAs($user)->getJson('/api/billing/invoices/'.billsOf($tenant)->first()->id)
        ->assertJsonCount(1, 'data.lines');
});
