<?php

declare(strict_types=1);

use App\Models\AddOn;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TenantAddOn;
use App\Models\User;
use App\Services\AddOns\AddOnManager;
use App\Services\Billing\CreditNoteManager;
use App\Services\Billing\InvoiceManager;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Cliente con plan Básico desde el 01/01/2026 (anual a S/ 1500 o mensual a S/ 150). */
function creditClient(string $cycle = 'yearly'): Tenant
{
    test()->travelTo(CarbonImmutable::parse('2026-01-01 09:00'));
    $tenant = Tenant::factory()->create();
    app(MembershipManager::class)->create($tenant, [
        'plan' => 'basico', 'billing_cycle' => $cycle, 'price' => $cycle === 'yearly' ? '1500' : '150',
        'starts_at' => '2026-01-01', 'ends_at' => '2027-12-31',
    ]);
    app(TenantModules::class)->sync($tenant->refresh(), app(TenantModules::class)->defaultsFor('basico'));

    return $tenant;
}

function contractBrandingOn(Tenant $tenant, string $date, string $key = 'branding'): TenantAddOn
{
    test()->travelTo(CarbonImmutable::parse("{$date} 10:00"));

    return app(AddOnManager::class)->contract($tenant, AddOn::query()->where('key', $key)->firstOrFail());
}

function cancelOn(TenantAddOn $contract, string $date): void
{
    test()->travelTo(CarbonImmutable::parse("{$date} 11:00"));
    app(AddOnManager::class)->cancel($contract->fresh(), null, 'Ya no lo usa');
}

/** @return Collection<int, CreditNote> */
function creditNotesOf(Tenant $tenant): Collection
{
    return CreditNote::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->orderBy('id')->get();
}

function prorationOf(TenantAddOn $contract): Invoice
{
    return Invoice::query()->withoutGlobalScope(TenantScope::class)->where('tenant_add_on_id', $contract->id)->firstOrFail();
}

it('credits the unused months of a yearly service against the unpaid proration', function () {
    $tenant = creditClient();
    $contract = contractBrandingOn($tenant, '2026-03-15');
    $proration = prorationOf($contract);
    expect((string) $proration->total)->toBe('637.20');

    cancelOn($contract, '2026-06-10');

    $note = creditNotesOf($tenant)->sole();
    expect($note->code())->toBe('BC01-00000001')
        ->and($note->invoice_id)->toBe($proration->id)
        ->and((string) $note->subtotal)->toBe('360.00')
        ->and((string) $note->total)->toBe('424.80')
        ->and($note->lines[0]['description'])->toBe('Devolución del servicio Marca en el login: 6 meses no usados del 01/07/2026 al 31/12/2026')
        ->and($proration->fresh()->balance())->toBe(212.40)
        ->and($proration->fresh()->status->value)->toBe('issued')
        ->and(app(CreditNoteManager::class)->availableFor($tenant->id))->toBe(0.0);
});

it('keeps the credit as balance in favor when the invoice was paid and uses it on the next one', function () {
    $tenant = creditClient();
    $contract = contractBrandingOn($tenant, '2026-03-15');
    app(InvoiceManager::class)->recordPayment(prorationOf($contract), ['amount' => '637.20', 'method' => 'transfer']);

    cancelOn($contract, '2026-06-10');
    expect(app(CreditNoteManager::class)->availableFor($tenant->id))->toBe(424.80);

    test()->travelTo(CarbonImmutable::parse('2027-01-01 06:00'));
    app(InvoiceManager::class)->runDaily();

    $renewal = Invoice::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
    expect((string) $renewal->total)->toBe('1770.00')
        ->and($renewal->balance())->toBe(1345.20)
        ->and(app(CreditNoteManager::class)->availableFor($tenant->id))->toBe(0.0);
});

it('marks an invoice paid when the balance in favor covers it', function () {
    $tenant = creditClient();
    $contract = contractBrandingOn($tenant, '2026-03-15');
    app(InvoiceManager::class)->recordPayment(prorationOf($contract), ['amount' => '637.20', 'method' => 'transfer']);
    cancelOn($contract, '2026-06-10');

    $invoice = app(InvoiceManager::class)->issue($tenant, '100', 'Capacitación');

    expect($invoice->status->value)->toBe('paid')
        ->and((float) $invoice->creditedAmount())->toBe(118.0)
        ->and(app(CreditNoteManager::class)->availableFor($tenant->id))->toBe(306.80);
});

it('credits a service billed in the yearly invoice', function () {
    $tenant = creditClient();
    contractBrandingOn($tenant, '2026-03-15');
    test()->travelTo(CarbonImmutable::parse('2027-01-01 06:00'));
    app(InvoiceManager::class)->runDaily();
    $renewal = Invoice::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();

    cancelOn(TenantAddOn::query()->forTenant($tenant->id)->sole(), '2027-03-10');

    $note = creditNotesOf($tenant)->sole();
    expect($note->invoice_id)->toBe($renewal->id)
        ->and((string) $note->subtotal)->toBe('540.00');
});

it('issues no credit for monthly memberships, the last month of the year or a plan change', function () {
    $monthly = creditClient('monthly');
    cancelOn(contractBrandingOn($monthly, '2026-03-15'), '2026-03-20');

    $december = creditClient();
    cancelOn(contractBrandingOn($december, '2026-03-15'), '2026-12-05');

    // Reportes viene incluido en Intermedio: el cambio de plan lo cancela sin nota.
    $planChange = creditClient();
    $reports = contractBrandingOn($planChange, '2026-03-15', 'reports');
    test()->travelTo(CarbonImmutable::parse('2026-06-10 12:00'));
    app(AddOnManager::class)->reconcileWithPlan($planChange, 'intermedio');

    expect($reports->fresh()->status->value)->toBe('cancelled')
        ->and(CreditNote::query()->withoutGlobalScope(TenantScope::class)->count())->toBe(0);
});

it('gives the credit back when an invoice that used it is voided, and does not void a credited invoice', function () {
    $tenant = creditClient();
    $contract = contractBrandingOn($tenant, '2026-03-15');
    app(InvoiceManager::class)->recordPayment(prorationOf($contract), ['amount' => '637.20', 'method' => 'transfer']);
    cancelOn($contract, '2026-06-10');
    $invoice = app(InvoiceManager::class)->issue($tenant, '1000', 'Implementación');
    expect($invoice->balance())->toBe(755.20)
        ->and(app(CreditNoteManager::class)->availableFor($tenant->id))->toBe(0.0);

    app(InvoiceManager::class)->void($invoice, 'Error de concepto');
    expect(app(CreditNoteManager::class)->availableFor($tenant->id))->toBe(424.80);

    $second = creditClient();
    $other = contractBrandingOn($second, '2026-03-15');
    cancelOn($other, '2026-06-10');
    expect(fn () => app(InvoiceManager::class)->void(prorationOf($other), 'x'))->toThrow(ValidationException::class);
});

it('only accepts payments up to the balance left after credits', function () {
    $tenant = creditClient();
    $contract = contractBrandingOn($tenant, '2026-03-15');
    cancelOn($contract, '2026-06-10');

    expect(fn () => app(InvoiceManager::class)->recordPayment(prorationOf($contract), ['amount' => '637.20', 'method' => 'transfer']))
        ->toThrow(ValidationException::class);

    app(InvoiceManager::class)->recordPayment(prorationOf($contract), ['amount' => '212.40', 'method' => 'transfer']);
    expect(prorationOf($contract)->status->value)->toBe('paid');
});

it('shows credit notes and the balance in favor to the client and the platform', function () {
    $tenant = creditClient();
    $contract = contractBrandingOn($tenant, '2026-03-15');
    app(InvoiceManager::class)->recordPayment(prorationOf($contract), ['amount' => '637.20', 'method' => 'transfer']);
    cancelOn($contract, '2026-06-10');
    $user = User::factory()->for($tenant)->create();
    $note = creditNotesOf($tenant)->sole();

    $this->actingAs($user)->getJson('/api/billing/summary')->assertJsonPath('data.credit_balance', '424.80');
    $this->actingAs($user)->getJson('/api/billing/credit-notes')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'BC01-00000001')
        ->assertJsonPath('data.0.remaining', '424.80')
        ->assertJsonPath('data.0.invoice.code', prorationOf($contract)->code());
    $this->actingAs($user)->getJson("/api/billing/credit-notes/{$note->id}")->assertOk()->assertJsonPath('data.reason', 'Cancelación del servicio Marca en el login');

    $this->actingAs(User::factory()->create())->getJson("/api/billing/credit-notes/{$note->id}")->assertNotFound();

    $this->actingAs(platformOwner())->getJson("/api/admin/credit-notes?client_id={$tenant->id}")
        ->assertOk()
        ->assertJsonPath('data.0.tenant.id', $tenant->id);
});

it('reports what was settled with credit on the invoice', function () {
    $tenant = creditClient();
    $contract = contractBrandingOn($tenant, '2026-03-15');
    cancelOn($contract, '2026-06-10');

    $this->actingAs(platformOwner())->getJson('/api/admin/invoices/'.prorationOf($contract)->id)
        ->assertJsonPath('data.credited', '424.80')
        ->assertJsonPath('data.balance', '212.40');
});
