<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use Illuminate\Support\Facades\Notification;

it('shows the client three public plans and marks its current one', function () {
    $user = User::factory()->create();
    app(MembershipManager::class)->create($user->tenant, [
        'plan' => 'intermedio', 'billing_cycle' => 'monthly', 'price' => '450',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
    ]);

    $data = $this->actingAs($user)->getJson('/api/membership')->assertOk()->json('data');

    expect(collect($data['plans'])->pluck('key')->all())->toBe(['basico', 'intermedio', 'avanzado'])
        ->and($data['current_plan'])->toBe('intermedio')
        ->and($data['plans'][0]['quotas'])->toBe(['whatsapp' => 1000, 'sms' => 1000, 'email' => 1000])
        ->and($data['plans'][0]['monthly_price'])->toBe('150.00');
});

it('includes a private plan when it is the client current plan', function () {
    $user = User::factory()->create();
    app(MembershipManager::class)->create($user->tenant, [
        'plan' => 'empresarial', 'billing_cycle' => 'yearly', 'price' => '20000',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addYear()->toDateString(),
    ]);

    $keys = collect($this->actingAs($user)->getJson('/api/membership')->json('data.plans'))->pluck('key');

    expect($keys)->toContain('empresarial');
});

it('lets the general administrator edit a plan with audit', function () {
    $plan = Plan::query()->where('key', 'basico')->firstOrFail();

    $this->actingAs(platformOwner())->putJson("/api/admin/plans/{$plan->id}", [
        'name' => 'Inicial',
        'description' => 'Plan de entrada',
        'monthly_price' => 199.9,
        'quotas' => ['whatsapp' => 1500, 'sms' => 1000, 'email' => null],
        'modules' => ['sms', 'whatsapp'],
        'is_public' => true,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Inicial')
        ->assertJsonPath('data.monthly_price', '199.90')
        ->assertJsonPath('data.quotas.email', null);

    expect(AuditLog::query()->where('action', 'plan.updated')->exists())->toBeTrue();
});

it('does not change existing memberships when the catalog changes', function () {
    $user = User::factory()->create();
    $membership = app(MembershipManager::class)->create($user->tenant, [
        'plan' => 'basico', 'billing_cycle' => 'monthly', 'price' => '150', 'quotas' => ['sms' => 1000],
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
    ]);
    Plan::query()->where('key', 'basico')->update(['monthly_price' => 999]);

    expect((string) $membership->fresh()->price)->toBe('150.00');
});

it('keeps clients out of plan management', function () {
    $plan = Plan::query()->firstOrFail();

    $this->actingAs(User::factory()->create())->putJson("/api/admin/plans/{$plan->id}", [
        'name' => 'X', 'quotas' => [], 'modules' => [], 'is_public' => true,
    ])->assertForbidden();
});

function planPayload(array $overrides = []): array
{
    return [
        'name' => 'Pyme Plus',
        'description' => 'Para pymes con varios locales',
        'monthly_price' => 690,
        'quotas' => ['whatsapp' => 8000, 'sms' => 3000, 'email' => 10000],
        'modules' => ['whatsapp', 'sms', 'email', 'reports'],
        'is_public' => true,
        ...$overrides,
    ];
}

it('creates a new plan with a stable key and its default modules', function () {
    $response = $this->actingAs(platformOwner())->postJson('/api/admin/plans', planPayload())->assertCreated();

    $response->assertJsonPath('data.key', 'pyme-plus')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.monthly_price', '690.00')
        ->assertJsonPath('data.clients_count', 0);

    expect(app(TenantModules::class)->defaultsFor('pyme-plus'))->toBe(['whatsapp', 'sms', 'email', 'reports']);
});

it('rejects a duplicated plan name', function () {
    $this->actingAs(platformOwner())->postJson('/api/admin/plans', planPayload(['name' => 'Intermedio']))
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('uses a new plan for clients and memberships', function () {
    Notification::fake();
    $owner = platformOwner();
    $this->actingAs($owner)->postJson('/api/admin/plans', planPayload())->assertCreated();

    $client = $this->actingAs($owner)->postJson('/api/admin/clients', [
        'type' => 'company', 'name' => 'Pyme SAC', 'document_type' => 'ruc', 'document_number' => '20131312955',
        'plan' => 'pyme-plus', 'admin_first_name' => 'Ana', 'admin_last_name' => 'Ríos', 'admin_email' => 'ana@pyme.pe',
    ])->assertCreated();

    expect($client->json('data.plan'))->toBe('pyme-plus')
        ->and($client->json('data.modules'))->toEqualCanonicalizing(['whatsapp', 'sms', 'email', 'reports']);
});

it('deactivates a plan: no new memberships, current clients keep it, hidden from others', function () {
    $owner = platformOwner();
    $basico = Plan::query()->where('key', 'basico')->firstOrFail();
    $customer = User::factory()->create();
    app(MembershipManager::class)->create($customer->tenant, [
        'plan' => 'basico', 'billing_cycle' => 'monthly', 'price' => '150',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($owner)->postJson("/api/admin/plans/{$basico->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.clients_count', 1);

    $other = Tenant::factory()->create();
    $this->actingAs($owner)->postJson("/api/admin/clients/{$other->id}/memberships", [
        'plan' => 'basico', 'billing_cycle' => 'monthly', 'price' => '150',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('plan');

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $mine = collect($this->actingAs($customer->fresh())->getJson('/api/membership')->json('data.plans'))->pluck('key');
    expect($mine)->toContain('basico');

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $stranger = User::factory()->create();
    $theirs = collect($this->actingAs($stranger)->getJson('/api/membership')->json('data.plans'))->pluck('key');
    expect($theirs)->not->toContain('basico');

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs($owner)->postJson("/api/admin/plans/{$basico->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);
});
