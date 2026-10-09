<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\SetPasswordLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a reset link to an existing user', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ana@demo.pe']);

    $this->postJson('/api/forgot-password', ['email' => 'ana@demo.pe'])->assertOk();

    Notification::assertSentTo($user, SetPasswordLink::class, fn (SetPasswordLink $n) => ! $n->invitation);
});

it('answers the same for unknown emails', function () {
    Notification::fake();

    $this->postJson('/api/forgot-password', ['email' => 'nadie@demo.pe'])
        ->assertOk()
        ->assertJsonPath('message', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.');

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create(['email' => 'ana@demo.pe']);
    $token = Password::broker('users')->createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => 'ana@demo.pe',
        'password' => 'NuevaClave123',
        'password_confirmation' => 'NuevaClave123',
    ])->assertOk();

    $this->postJson('/api/login', ['email' => 'ana@demo.pe', 'password' => 'NuevaClave123'])->assertOk();
});

it('rejects an invalid token', function () {
    User::factory()->create(['email' => 'ana@demo.pe']);

    $this->postJson('/api/reset-password', [
        'token' => 'falso',
        'email' => 'ana@demo.pe',
        'password' => 'NuevaClave123',
        'password_confirmation' => 'NuevaClave123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('keeps invitation links valid for days while reset links expire in an hour', function () {
    $user = User::factory()->create(['email' => 'ana@demo.pe']);
    $token = Password::broker('invitations')->createToken($user);

    $this->travel(3)->days();

    $payload = ['token' => $token, 'email' => 'ana@demo.pe', 'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'];

    $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
    $this->postJson('/api/reset-password', [...$payload, 'invite' => true])->assertOk();
});

it('requires a reasonably strong password', function () {
    $user = User::factory()->create(['email' => 'ana@demo.pe']);
    $token = Password::broker('users')->createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token, 'email' => 'ana@demo.pe', 'password' => 'corta', 'password_confirmation' => 'corta',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});
