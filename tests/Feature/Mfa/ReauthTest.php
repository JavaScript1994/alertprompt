<?php

declare(strict_types=1);

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MfaDisabledNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();
});

it('blocks a sensitive action without re-auth with 403 reauth_required', function () {
    $this->actingAs($this->admin)->postJson('/api/mfa/recovery-codes/regenerate')
        ->assertForbidden()
        ->assertJsonPath('code', 'reauth_required')
        ->assertJsonPath('action', 'regenerate_recovery_codes');

    expect(AuthEvent::query()->where('event', AuthEventType::ReauthRequired)->exists())->toBeTrue();
});

it('allows the action for 15 minutes after password + TOTP, then asks again', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/reauth', ['action' => 'regenerate_recovery_codes', 'password' => 'password', 'code' => totp($this->admin)])
        ->assertOk();

    expect(AuthEvent::query()->where('event', AuthEventType::ReauthSuccess)->exists())->toBeTrue();

    $codes = $this->postJson('/api/mfa/recovery-codes/regenerate')->assertOk()->json('data.recovery_codes');
    expect($codes)->toHaveCount(8);

    $this->travel(14)->minutes();
    $this->postJson('/api/mfa/recovery-codes/regenerate')->assertOk();

    $this->travel(2)->minutes();
    $this->postJson('/api/mfa/recovery-codes/regenerate')->assertForbidden()->assertJsonPath('code', 'reauth_required');
});

it('confirms only the requested action', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/reauth', ['action' => 'regenerate_recovery_codes', 'password' => 'password', 'code' => totp($this->admin)])
        ->assertOk();

    $this->deleteJson('/api/mfa')->assertForbidden()->assertJsonPath('action', 'disable_mfa');
});

it('rejects a wrong password or code without saying which', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/reauth', ['action' => 'disable_mfa', 'password' => 'otra', 'code' => totp($this->admin)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->postJson('/api/reauth', ['action' => 'disable_mfa', 'password' => 'password', 'code' => '000000'])
        ->assertUnprocessable();

    expect(AuthEvent::query()->where('event', AuthEventType::ReauthFailed)->count())->toBe(2);
});

it('disables MFA with re-auth and notifies the administrators', function () {
    $user = User::factory()->withRole('client-user')->for($this->tenant)->create();

    $this->actingAs($user)
        ->postJson('/api/reauth', ['action' => 'disable_mfa', 'password' => 'password', 'code' => totp($user)])
        ->assertOk();
    $this->deleteJson('/api/mfa')->assertNoContent();

    expect($user->fresh()->hasMfaEnabled())->toBeFalse()
        ->and(AuthEvent::query()->where('event', AuthEventType::MfaDisabled)->exists())->toBeTrue();
    Notification::assertSentTo($this->admin, MfaDisabledNotification::class);
});

it('requires re-auth to export contact data', function () {
    $this->actingAs($this->admin)->getJson('/api/reports/export')
        ->assertForbidden()
        ->assertJsonPath('action', 'export_contacts');
});

it('requires re-auth to change a user role', function () {
    $user = User::factory()->withRole('client-user')->for($this->tenant)->create();

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}/role", ['role_id' => 1])
        ->assertForbidden()
        ->assertJsonPath('action', 'change_user_role');
});

it('does not ask for re-auth to revoke a consent (Ley 32323: immediate effect)', function () {
    $contact = Contact::factory()->for($this->tenant)->create();
    $consent = Consent::factory()->for($contact)->create(['revoked_at' => null]);

    $this->actingAs($this->admin)
        ->patchJson("/api/contacts/{$contact->id}/consents/{$consent->id}/revoke")
        ->assertOk();
});

it('lets an administrator reset the MFA of a user of the account, with re-auth', function () {
    $user = User::factory()->withRole('client-user')->for($this->tenant)->create();

    $this->actingAs($this->admin)->postJson("/api/users/{$user->id}/mfa/reset")->assertForbidden();

    $this->postJson('/api/reauth', ['action' => 'reset_user_mfa', 'password' => 'password', 'code' => totp($this->admin)])->assertOk();
    $this->postJson("/api/users/{$user->id}/mfa/reset")->assertNoContent();

    expect($user->fresh()->hasMfaEnabled())->toBeFalse()
        ->and(AuthEvent::query()->where('event', AuthEventType::MfaReset)->where('user_id', $user->id)->exists())->toBeTrue();
    Notification::assertSentTo($user, MfaDisabledNotification::class);
});

it('lets the platform reset the MFA of a client administrator', function () {
    $owner = platformOwner();

    $this->actingAs($owner)
        ->postJson('/api/reauth', ['action' => 'reset_user_mfa', 'password' => 'password', 'code' => totp($owner)])
        ->assertOk();
    $this->postJson("/api/admin/clients/{$this->tenant->id}/users/{$this->admin->id}/mfa/reset")->assertNoContent();

    expect($this->admin->fresh()->hasMfaEnabled())->toBeFalse();
});
