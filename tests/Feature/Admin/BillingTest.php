<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\SunatStatus;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceManager;
use App\Services\Memberships\MembershipManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

function invoicesOf(Tenant $tenant): Builder
{
    return Invoice::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id);
}

it('issues a factura with IGV for a client with RUC, as an internal proforma', function () {
    $client = Tenant::factory()->create(['name' => 'Andina SAC', 'document_type' => 'ruc', 'document_number' => '20131312955']);

    $response = $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$client->id}/invoices", [
        'description' => 'Implementación inicial', 'subtotal' => 100,
    ])->assertCreated();

    $response->assertJsonPath('data.code', 'F001-00000001')
        ->assertJsonPath('data.document_type', 'factura')
        ->assertJsonPath('data.subtotal', '100.00')
        ->assertJsonPath('data.igv', '18.00')
        ->assertJsonPath('data.total', '118.00')
        ->assertJsonPath('data.is_electronic', false)
        ->assertJsonPath('data.sunat_status', SunatStatus::NotSent->value)
        ->assertJsonPath('data.customer.document_number', '20131312955');
});

it('issues boletas for clients without RUC with their own correlative', function () {
    $owner = platformOwner();
    $person = Tenant::factory()->individual()->create(['document_type' => 'dni', 'document_number' => '46779354']);

    foreach (['00000001', '00000002'] as $expected) {
        $this->actingAs($owner)->postJson("/api/admin/clients/{$person->id}/invoices", ['description' => 'X', 'subtotal' => 10])
            ->assertJsonPath('data.code', "B001-{$expected}");
    }
});

it('marks the invoice paid once payments cover the total', function () {
    $owner = platformOwner();
    $invoice = app(InvoiceManager::class)->issue(Tenant::factory()->create(), '100', 'Plan');

    $this->actingAs($owner)->postJson("/api/admin/invoices/{$invoice->id}/payments", ['amount' => 50, 'method' => 'yape', 'reference' => 'OP-1'])->assertCreated();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);

    $this->actingAs($owner)->postJson("/api/admin/invoices/{$invoice->id}/payments", ['amount' => 68, 'method' => 'transfer'])->assertCreated();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(AuditLog::query()->where('action', 'payment.recorded')->count())->toBe(2);
});

it('rejects payments above the balance and card payments recorded by hand', function () {
    $owner = platformOwner();
    $invoice = app(InvoiceManager::class)->issue(Tenant::factory()->create(), '100', 'Plan');

    $this->actingAs($owner)->postJson("/api/admin/invoices/{$invoice->id}/payments", ['amount' => 200, 'method' => 'cash'])
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->actingAs($owner)->postJson("/api/admin/invoices/{$invoice->id}/payments", ['amount' => 10, 'method' => 'card'])
        ->assertUnprocessable()->assertJsonValidationErrors('method');
});

it('voids only unpaid invoices', function () {
    $owner = platformOwner();
    $manager = app(InvoiceManager::class);
    $unpaid = $manager->issue(Tenant::factory()->create(), '100', 'Plan');
    $paid = $manager->issue(Tenant::factory()->create(), '100', 'Plan');
    $manager->recordPayment($paid, ['amount' => 118, 'method' => 'cash']);

    $this->actingAs($owner)->postJson("/api/admin/invoices/{$unpaid->id}/void", ['reason' => 'Error de monto'])
        ->assertOk()->assertJsonPath('data.status', 'void');
    $this->actingAs($owner)->postJson("/api/admin/invoices/{$paid->id}/void", ['reason' => 'x'])
        ->assertUnprocessable();
});

it('invoices a membership period once, including on activation', function () {
    $client = Tenant::factory()->create();
    app(MembershipManager::class)->create($client, [
        'plan' => 'growth', 'billing_cycle' => 'monthly', 'price' => '450',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonths(3)->toDateString(),
    ]);

    expect(invoicesOf($client)->count())->toBe(1)
        ->and(invoicesOf($client)->first()->total)->toBe('531.00');

    $manager = app(InvoiceManager::class);
    $manager->runDaily();
    expect(invoicesOf($client)->count())->toBe(1);

    $manager->runDaily(CarbonImmutable::today()->addMonth());
    expect(invoicesOf($client)->count())->toBe(2);
});

it('alerts overdue invoices', function () {
    $client = Tenant::factory()->create();
    $invoice = app(InvoiceManager::class)->issue($client, '100', 'Plan');
    $invoice->update(['due_date' => now()->subDays(3)]);

    $this->artisan('billing:daily')->assertSuccessful();

    expect(Alert::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $client->id)->where('type', 'invoice.overdue')->exists())->toBeTrue();
});

it('shows the client only its own invoices, balance and payment instructions', function () {
    $user = User::factory()->create();
    app(InvoiceManager::class)->issue($user->tenant, '100', 'Mío');
    app(InvoiceManager::class)->issue(Tenant::factory()->create(), '999', 'Ajeno');

    $this->actingAs($user)->getJson('/api/billing/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'Mío');
    $this->actingAs($user)->getJson('/api/billing/summary')
        ->assertOk()
        ->assertJsonPath('data.balance', '118.00')
        ->assertJsonPath('data.cards_enabled', false);
});

it('does not accept cards while no gateway is configured', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/billing/payment-methods', ['token' => 'tkn_test_123'])
        ->assertUnprocessable()->assertJsonValidationErrors('token');
});

it('keeps billing data away from users without the permission', function () {
    $this->actingAs(User::factory()->withRole('client-user')->create())->getJson('/api/billing/invoices')->assertForbidden();
    $this->actingAs(User::factory()->create())->getJson('/api/admin/invoices')->assertForbidden();
});
