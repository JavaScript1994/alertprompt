<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mfa\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(RecoveryCodeService::class);
    $this->user = User::factory()->create();
});

it('generates eight codes and stores only their hashes, encrypted', function () {
    $codes = $this->service->generate($this->user);

    expect($codes)->toHaveCount(8)->each->toMatch('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/');

    $raw = (string) DB::table('users')->where('id', $this->user->id)->value('two_factor_recovery_codes');
    foreach ($codes as $code) {
        expect($raw)->not->toContain($code);
    }

    $stored = $this->user->fresh()->two_factor_recovery_codes;
    expect($stored)->toHaveCount(8);
    foreach ($stored as $index => $hash) {
        expect(Hash::isHashed($hash))->toBeTrue()
            ->and($hash)->not->toBe($codes[$index]);
    }
});

it('consumes a code once', function () {
    $codes = $this->service->generate($this->user);

    expect($this->service->consume($this->user, $codes[3]))->toBeTrue()
        ->and($this->service->consume($this->user, $codes[3]))->toBeFalse()
        ->and($this->service->remaining($this->user->fresh()))->toBe(7);
});

it('accepts lowercase and missing dash', function () {
    $codes = $this->service->generate($this->user);

    expect($this->service->consume($this->user, strtolower(str_replace('-', '', $codes[0]))))->toBeTrue();
});

it('rejects an unknown code', function () {
    $this->service->generate($this->user);

    expect($this->service->consume($this->user, 'AAAAA-AAAAA'))->toBeFalse()
        ->and($this->service->remaining($this->user->fresh()))->toBe(8);
});

it('asks to regenerate when two or fewer codes are left', function () {
    $codes = $this->service->generate($this->user);

    foreach (array_slice($codes, 0, 5) as $code) {
        $this->service->consume($this->user, $code);
    }
    expect($this->service->shouldRegenerate($this->user->fresh()))->toBeFalse();

    $this->service->consume($this->user, $codes[5]);
    expect($this->service->shouldRegenerate($this->user->fresh()))->toBeTrue();
});
