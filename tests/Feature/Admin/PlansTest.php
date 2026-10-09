<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\Memberships\MembershipManager;

it('shows the client three public plans and marks its current one', function () {
    $user = User::factory()->create();
    app(MembershipManager::class)->create($user->tenant, [
        'plan' => 'growth', 'billing_cycle' => 'monthly', 'price' => '450',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
    ]);

    $data = $this->actingAs($user)->getJson('/api/membership')->assertOk()->json('data');

    expect(collect($data['plans'])->pluck('key')->all())->toBe(['starter', 'growth', 'scale'])
        ->and($data['current_plan'])->toBe('growth')
        ->and($data['plans'][0]['quotas'])->toBe(['whatsapp' => 1000, 'sms' => 1000, 'email' => 1000])
        ->and($data['plans'][0]['monthly_price'])->toBe('150.00');
});

it('includes a private plan when it is the client current plan', function () {
    $user = User::factory()->create();
    app(MembershipManager::class)->create($user->tenant, [
        'plan' => 'enterprise', 'billing_cycle' => 'yearly', 'price' => '20000',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addYear()->toDateString(),
    ]);

    $keys = collect($this->actingAs($user)->getJson('/api/membership')->json('data.plans'))->pluck('key');

    expect($keys)->toContain('enterprise');
});

it('lets the general administrator edit a plan with audit', function () {
    $plan = Plan::query()->where('key', 'starter')->firstOrFail();

    $this->actingAs(platformOwner())->putJson("/api/admin/plans/{$plan->id}", [
        'name' => 'Inicial',
        'description' => 'Plan de entrada',
        'monthly_price' => 199.9,
        'quotas' => ['whatsapp' => 1500, 'sms' => 1000, 'email' => null],
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
        'plan' => 'starter', 'billing_cycle' => 'monthly', 'price' => '150', 'quotas' => ['sms' => 1000],
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
    ]);
    Plan::query()->where('key', 'starter')->update(['monthly_price' => 999]);

    expect((string) $membership->fresh()->price)->toBe('150.00');
});

it('keeps clients out of plan management', function () {
    $plan = Plan::query()->firstOrFail();

    $this->actingAs(User::factory()->create())->putJson("/api/admin/plans/{$plan->id}", [
        'name' => 'X', 'quotas' => [], 'is_public' => true,
    ])->assertForbidden();
});
