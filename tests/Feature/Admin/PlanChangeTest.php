<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Alert;
use App\Models\Membership;
use App\Models\PlanChangeRequest;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use Carbon\CarbonImmutable;

function clientOnPlan(string $plan = 'basico', string $startsAt = 'today'): User
{
    $user = User::factory()->create();
    app(MembershipManager::class)->startFromCatalog($user->tenant, $plan, CarbonImmutable::parse($startsAt));
    $user->tenant->refresh();

    return $user;
}

function pendingChange(User $user): PlanChangeRequest
{
    return PlanChangeRequest::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $user->tenant_id)->latest('id')->firstOrFail();
}

it('lets the client request a plan change and alerts the platform', function () {
    $user = clientOnPlan();

    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'avanzado', 'comment' => 'Vamos a crecer'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_plan_name', 'Básico')
        ->assertJsonPath('data.requested_plan_name', 'Avanzado');

    $this->actingAs($user)->getJson('/api/membership')->assertJsonPath('data.pending_request.requested_plan', 'avanzado');

    expect(Alert::query()->withoutGlobalScope(TenantScope::class)->where('type', 'plan_change.requested')->whereNull('resolved_at')->exists())->toBeTrue();
});

it('rejects requesting the same plan, an inactive one, or a second pending request', function () {
    $user = clientOnPlan();

    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'basico'])->assertUnprocessable()->assertJsonValidationErrors('plan');
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'empresarial'])->assertUnprocessable();

    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'intermedio'])->assertCreated();
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'avanzado'])->assertUnprocessable();
});

it('lets the client cancel its pending request', function () {
    $user = clientOnPlan();
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'intermedio'])->assertCreated();

    $this->actingAs($user)->deleteJson('/api/membership/plan-change')->assertNoContent();

    expect(pendingChange($user)->status->value)->toBe('cancelled');
});

it('approves a change effective today: new membership, plan and modules', function () {
    $user = clientOnPlan('basico');
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'avanzado'])->assertCreated();
    $change = pendingChange($user);

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs(platformOwner())->postJson("/api/admin/plan-changes/{$change->id}/approve", ['when' => 'now'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.effective_from', now()->toDateString());

    $active = Membership::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $user->tenant_id)->where('status', MembershipStatus::Active)->sole();
    expect($active->plan)->toBe('avanzado')
        ->and((string) $active->price)->toBe('1200.00')
        ->and($user->tenant->fresh()->plan)->toBe('avanzado')
        ->and(app(TenantModules::class)->enabledFor($user->tenant_id))->toContain('whatsapp', 'reports')
        ->and(Alert::query()->withoutGlobalScope(TenantScope::class)->where('type', 'plan_change.requested')->whereNull('resolved_at')->exists())->toBeFalse();
});

it('approves a change for the next billing period without leaving a gap', function () {
    $user = clientOnPlan('basico', now()->subDays(10)->toDateString());
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'intermedio'])->assertCreated();
    $change = pendingChange($user);

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs(platformOwner())->postJson("/api/admin/plan-changes/{$change->id}/approve", ['when' => 'next_period'])->assertOk();

    $memberships = Membership::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $user->tenant_id)->orderBy('starts_at')->get();
    $nextStart = now()->subDays(10)->addMonthNoOverflow()->startOfDay();

    expect($memberships)->toHaveCount(2)
        ->and($memberships[0]->status)->toBe(MembershipStatus::Active)
        ->and($memberships[0]->ends_at->toDateString())->toBe($nextStart->copy()->subDay()->toDateString())
        ->and($memberships[1]->status)->toBe(MembershipStatus::Scheduled)
        ->and($memberships[1]->plan)->toBe('intermedio')
        ->and($memberships[1]->starts_at->toDateString())->toBe($nextStart->toDateString());
});

it('rejects a change with a reason the client can see', function () {
    $user = clientOnPlan();
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'avanzado'])->assertCreated();
    $change = pendingChange($user);

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs(platformOwner())->postJson("/api/admin/plan-changes/{$change->id}/reject", ['note' => 'Primero regularicemos el pago pendiente'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs($user)->getJson('/api/membership')
        ->assertJsonPath('data.pending_request', null)
        ->assertJsonPath('data.last_decision.decision_note', 'Primero regularicemos el pago pendiente');
});

it('lists pending requests for the platform only', function () {
    $user = clientOnPlan();
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'avanzado'])->assertCreated();

    $this->actingAs($user)->getJson('/api/admin/plan-changes')->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs(platformOwner())->getJson('/api/admin/plan-changes')
        ->assertOk()
        ->assertJsonPath('data.0.tenant.id', $user->tenant_id);
});

it('does not let regular users or support mode request a change', function () {
    $user = User::factory()->withRole('client-user')->create();
    $this->actingAs($user)->postJson('/api/membership/plan-change', ['plan' => 'avanzado'])->assertForbidden();

    $client = clientOnPlan();
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs(platformOwner())->postJson("/api/admin/clients/{$client->tenant_id}/impersonate")->assertNoContent();
    $this->postJson('/api/membership/plan-change', ['plan' => 'avanzado'])->assertForbidden();
});
