<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use App\Notifications\EmailChangedNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

// Cambiar correo exige re-autenticación (reauth:change_user_email).
beforeEach(function () {
    $this->reauthConfirmed = true;
    Storage::fake('local');
    Notification::fake();
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();
});

$personal = [
    'first_name' => 'Rosa',
    'last_name' => 'Quispe Mamani',
    'job_title' => 'Jefa de marketing',
    'birth_date' => '1990-04-12',
    'phone' => '01 234 5678',
    'mobile' => '+51 987 654 321',
];

it('lets every role edit their own personal data from My profile', function (string $role) use ($personal) {
    $user = $role === 'platform-owner' ? platformOwner() : User::factory()->for($this->tenant)->withRole($role)->create();

    $this->actingAs($user)->putJson('/api/profile', $personal)
        ->assertOk()
        ->assertJsonPath('data.name', 'Rosa Quispe Mamani')
        ->assertJsonPath('data.first_name', 'Rosa')
        ->assertJsonPath('data.birth_date', '1990-04-12')
        ->assertJsonPath('data.mobile', '+51 987 654 321');

    expect($user->fresh()->job_title)->toBe('Jefa de marketing');
})->with(['client-viewer', 'client-user', 'client-admin', 'platform-owner']);

it('does not change email or role from My profile', function () use ($personal) {
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create(['email' => 'yo@cliente.pe']);

    $this->actingAs($user)->putJson('/api/profile', [...$personal, 'email' => 'otro@cliente.pe', 'role_id' => 1])->assertOk();

    expect($user->fresh()->email)->toBe('yo@cliente.pe')
        ->and($user->fresh()->hasRole('client-user'))->toBeTrue();
});

it('validates personal data', function () {
    $this->actingAs($this->admin)->putJson('/api/profile', [
        'first_name' => '',
        'last_name' => 'Pérez',
        'birth_date' => now()->subYears(10)->toDateString(),
        'mobile' => 'llámame',
    ])->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'birth_date', 'mobile']);
});

it('keeps birth date, phones, job title and photo optional', function () {
    $this->actingAs($this->admin)->putJson('/api/profile', ['first_name' => 'Ana', 'last_name' => 'Ríos'])
        ->assertOk()
        ->assertJsonPath('data.birth_date', null);
});

it('audits which fields changed, never their values', function () use ($personal) {
    $this->actingAs($this->admin)->putJson('/api/profile', $personal)->assertOk();

    $log = AuditLog::query()->where('action', 'user.profile_updated')->sole();
    expect($log->metadata['fields'])->toContain('birth_date')
        ->and(json_encode($log->metadata))->not->toContain('1990-04-12');
});

it('stores the photo on the private disk and serves it only through the API', function () {
    $this->actingAs($this->admin)->post('/api/profile/photo', ['photo' => fakePhoto()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.photo_url', fn (string $url) => str_starts_with($url, '/api/profile/photo?v='));

    $path = $this->admin->fresh()->photo_path;
    expect($path)->toStartWith("user-photos/{$this->tenant->id}/");
    Storage::disk('local')->assertExists($path);

    $this->get('/api/profile/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('replaces and removes the photo, deleting the old file', function () {
    $this->actingAs($this->admin)->post('/api/profile/photo', ['photo' => fakePhoto()], ['Accept' => 'application/json'])->assertOk();
    $first = $this->admin->fresh()->photo_path;

    $this->post('/api/profile/photo', ['photo' => fakePhoto('otra.png')], ['Accept' => 'application/json'])->assertOk();
    Storage::disk('local')->assertMissing($first);

    $this->deleteJson('/api/profile/photo')->assertOk()->assertJsonPath('data.photo_url', null);
    expect($this->admin->fresh()->photo_path)->toBeNull();
});

it('rejects photos that are not images or are too small', function (UploadedFile $file) {
    $this->actingAs($this->admin)->post('/api/profile/photo', ['photo' => $file], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    'chica' => fn () => fakePhoto('mini.png', small: true),
    'pesada' => fn () => UploadedFile::fake()->create('grande.png', 3000, 'image/png'),
]);

it('lets an administrator edit the personal data of a user without re-auth', function () use ($personal) {
    $this->reauthConfirmed = false;
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create();

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", $personal)
        ->assertOk()
        ->assertJsonPath('data.name', 'Rosa Quispe Mamani')
        ->assertJsonPath('data.job_title', 'Jefa de marketing');
});

it('does not let a user without users.manage edit others', function () use ($personal) {
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create();

    $this->actingAs($user)->putJson("/api/users/{$this->admin->id}", $personal)->assertForbidden();
    $this->actingAs($user)->post("/api/users/{$this->admin->id}/photo", ['photo' => fakePhoto()], ['Accept' => 'application/json'])->assertForbidden();
});

it('keeps photos and personal data inside the tenant', function () use ($personal) {
    $other = User::factory()->for(Tenant::factory())->withRole('client-user')->create();
    $other->forceFill(['photo_path' => 'user-photos/x/foto.png'])->save();

    $this->actingAs($this->admin)->getJson("/api/users/{$other->id}/photo")->assertNotFound();
    $this->putJson("/api/users/{$other->id}", $personal)->assertNotFound();
});

it('changes the login email only after confirming from the new address', function () {
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create(['email' => 'viejo@cliente.pe']);

    $this->actingAs($this->admin)->postJson("/api/users/{$user->id}/email", ['email' => 'Nuevo@Cliente.pe'])
        ->assertOk()
        ->assertJsonPath('data.email', 'viejo@cliente.pe')
        ->assertJsonPath('data.pending_email', 'nuevo@cliente.pe');

    $url = null;
    Notification::assertSentOnDemand(ConfirmEmailChange::class, function (ConfirmEmailChange $n, $channels, AnonymousNotifiable $to) use (&$url) {
        $url = $n->url;

        return $to->routes['mail'] === 'nuevo@cliente.pe';
    });

    $this->get($url)->assertRedirect('/login?email_changed=1');

    expect($user->fresh()->email)->toBe('nuevo@cliente.pe')
        ->and($user->fresh()->pending_email)->toBeNull();
    Notification::assertSentOnDemand(EmailChangedNotice::class, fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'viejo@cliente.pe');
});

it('requires re-auth to change a login email', function () {
    $this->reauthConfirmed = false;
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create();

    $this->actingAs($this->admin)->postJson("/api/users/{$user->id}/email", ['email' => 'nuevo@cliente.pe'])
        ->assertForbidden()
        ->assertJsonPath('action', 'change_user_email');
});

it('rejects a tampered or expired email change link', function () {
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create(['email' => 'viejo@cliente.pe']);
    $this->actingAs($this->admin)->postJson("/api/users/{$user->id}/email", ['email' => 'nuevo@cliente.pe'])->assertOk();

    $url = null;
    Notification::assertSentOnDemand(ConfirmEmailChange::class, function (ConfirmEmailChange $n) use (&$url) {
        $url = $n->url;

        return true;
    });

    $this->get(str_replace(sha1('nuevo@cliente.pe'), sha1('atacante@x.pe'), $url))->assertForbidden();

    $this->travel(25)->hours();
    $this->get($url)->assertForbidden();

    expect($user->fresh()->email)->toBe('viejo@cliente.pe');
});

it('does not change the email if someone took it in the meantime', function () {
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create(['email' => 'viejo@cliente.pe']);
    $this->actingAs($this->admin)->postJson("/api/users/{$user->id}/email", ['email' => 'nuevo@cliente.pe'])->assertOk();
    $url = null;
    Notification::assertSentOnDemand(ConfirmEmailChange::class, function (ConfirmEmailChange $n) use (&$url) {
        $url = $n->url;

        return true;
    });

    User::factory()->create(['email' => 'nuevo@cliente.pe']);

    $this->get($url)->assertRedirect('/login?email_change_failed=1');
    expect($user->fresh()->email)->toBe('viejo@cliente.pe');
});

it('rejects an email already used or pending for another user', function () {
    $user = User::factory()->for($this->tenant)->withRole('client-user')->create();
    User::factory()->create(['email' => 'ocupado@x.pe']);

    $this->actingAs($this->admin)->postJson("/api/users/{$user->id}/email", ['email' => 'ocupado@x.pe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('fills first name from the display name for legacy creations', function () {
    $user = User::factory()->create(['name' => 'Admin Empresa de Prueba']);

    expect($user->first_name)->toBe('Admin Empresa de Prueba')
        ->and($user->name)->toBe('Admin Empresa de Prueba');
});
