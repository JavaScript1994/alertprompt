<?php

declare(strict_types=1);

use App\Services\CampaignPacer;

beforeEach(function () {
    $this->pacer = new CampaignPacer(0.03);
});

it('does not pause when the failure ratio is under the threshold', function () {
    expect($this->pacer->shouldPause(sent: 500, failed: 10))->toBeFalse();
});

it('pauses when the failure ratio exceeds the threshold', function () {
    expect($this->pacer->shouldPause(sent: 500, failed: 20))->toBeTrue();
});

it('combines failed, blocked and reported for the ratio', function () {
    expect($this->pacer->shouldPause(sent: 100, failed: 2, blocked: 1, reported: 1))->toBeTrue();
});

it('does not pause exactly at the threshold, only strictly above it', function () {
    expect($this->pacer->shouldPause(sent: 100, failed: 3))->toBeFalse();
});

it('does not pause when there have not been any sends yet', function () {
    expect($this->pacer->shouldPause(sent: 0, failed: 0))->toBeFalse();
});
