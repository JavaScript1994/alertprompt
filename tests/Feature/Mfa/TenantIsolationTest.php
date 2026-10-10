<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->adminA = User::factory()->for(Tenant::factory())->create();
    $this->userB = User::factory()->withRole('client-user')->for(Tenant::factory())->create();
});

it('does not let tenant A reset the MFA of a user of tenant B', function () {
    $this->actingAs($this->adminA)
        ->postJson('/api/reauth', ['action' => 'reset_user_mfa', 'password' => 'password', 'code' => totp($this->adminA)])
        ->assertOk();

    $this->postJson("/api/users/{$this->userB->id}/mfa/reset")->assertNotFound();

    expect($this->userB->fresh()->hasMfaEnabled())->toBeTrue();
});

it('does not show the MFA state of tenant B users to tenant A', function () {
    $ids = collect($this->actingAs($this->adminA)->getJson('/api/users')->assertOk()->json('data'))->pluck('id');

    expect($ids)->not->toContain($this->userB->id);
});

it('only reports the MFA status of the authenticated user', function () {
    $this->actingAs($this->adminA)->getJson('/api/mfa/status')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonMissingPath('data.user_id');
});

it('does not let a client administrator use the platform reset route', function () {
    $this->actingAs($this->adminA)
        ->postJson('/api/admin/clients/'.$this->userB->tenant_id.'/users/'.$this->userB->id.'/mfa/reset')
        ->assertForbidden();
});
