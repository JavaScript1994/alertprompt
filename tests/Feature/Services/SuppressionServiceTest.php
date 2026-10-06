<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\SuppressionReason;
use App\Models\Suppression;
use App\Models\Tenant;
use App\Services\SuppressionService;

beforeEach(function () {
    $this->service = new SuppressionService;
});

it('detects a suppressed identifier for the given tenant and channel', function () {
    $tenant = Tenant::factory()->create();
    Suppression::factory()->for($tenant)->create([
        'channel' => Channel::WhatsApp->value,
        'identifier' => '+51987654321',
    ]);

    expect($this->service->isSuppressed($tenant->id, Channel::WhatsApp, '+51987654321'))->toBeTrue();
});

it('does not consider an identifier suppressed on a different channel', function () {
    $tenant = Tenant::factory()->create();
    Suppression::factory()->for($tenant)->create([
        'channel' => Channel::Sms->value,
        'identifier' => '+51987654321',
    ]);

    expect($this->service->isSuppressed($tenant->id, Channel::WhatsApp, '+51987654321'))->toBeFalse();
});

it('does not leak suppressions across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Suppression::factory()->for($tenantA)->create([
        'channel' => Channel::WhatsApp->value,
        'identifier' => '+51987654321',
    ]);

    expect($this->service->isSuppressed($tenantB->id, Channel::WhatsApp, '+51987654321'))->toBeFalse();
});

it('suppresses an identifier idempotently', function () {
    $tenant = Tenant::factory()->create();

    $this->service->suppress($tenant->id, Channel::Email, 'x@example.com', SuppressionReason::HardBounce);
    $this->service->suppress($tenant->id, Channel::Email, 'x@example.com', SuppressionReason::HardBounce);

    expect(Suppression::query()->count())->toBe(1);
});
