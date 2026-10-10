<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mfa\MfaService;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->google2fa = new Google2FA;
    $this->secret = $this->google2fa->generateSecretKey(32);
    $this->mfa = app(MfaService::class);
    $this->now = $this->google2fa->getTimestamp();
});

it('accepts the code of the current period and of ±1 period', function (int $offset) {
    $code = $this->google2fa->oathTotp($this->secret, $this->now + $offset);

    expect($this->mfa->verifyCode($this->secret, $code, atStep: $this->now))->toBe($this->now + $offset);
})->with([-1, 0, 1]);

it('rejects the code of ±2 periods', function (int $offset) {
    $code = $this->google2fa->oathTotp($this->secret, $this->now + $offset);

    expect($this->mfa->verifyCode($this->secret, $code, atStep: $this->now))->toBeFalse();
})->with([-2, 2]);

it('does not accept the same period twice (replay)', function () {
    $code = $this->google2fa->oathTotp($this->secret, $this->now);

    expect($this->mfa->verifyCode($this->secret, $code, lastStep: $this->now, atStep: $this->now))->toBeFalse();
});

it('rejects anything that is not six digits', function (string $code) {
    expect($this->mfa->verifyCode($this->secret, $code, atStep: $this->now))->toBeFalse();
})->with(['', '12345', '1234567', 'abcdef', '12 345']);

it('reads the last used period from a cache that returns strings (Redis)', function () {
    $user = (new User)->forceFill(['id' => 999, 'two_factor_secret' => $this->secret]);
    Cache::put("mfa:totp-last-step:{$user->id}", (string) ($this->now - 5));

    expect($this->mfa->verifyUserCode($user, $this->google2fa->oathTotp($this->secret, $this->now)))->toBeTrue();
});
