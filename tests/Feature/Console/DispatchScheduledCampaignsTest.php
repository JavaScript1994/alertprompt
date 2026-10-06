<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Jobs\DispatchCampaign;
use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Support\Facades\Bus;

it('dispatches only campaigns whose scheduled time has already arrived', function () {
    Bus::fake();

    $tenant = Tenant::factory()->create();

    $due = Campaign::factory()->for($tenant)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $future = Campaign::factory()->for($tenant)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_at' => now()->addHour(),
    ]);
    $alreadyRunning = Campaign::factory()->for($tenant)->create([
        'status' => CampaignStatus::Running,
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('campaigns:dispatch-scheduled')->assertSuccessful();

    Bus::assertDispatched(DispatchCampaign::class, fn ($job) => $job->campaignId === $due->id);
    Bus::assertNotDispatched(DispatchCampaign::class, fn ($job) => $job->campaignId === $future->id);
    Bus::assertNotDispatched(DispatchCampaign::class, fn ($job) => $job->campaignId === $alreadyRunning->id);
});

it('does nothing when there are no due campaigns', function () {
    Bus::fake();

    $this->artisan('campaigns:dispatch-scheduled')->assertSuccessful();

    Bus::assertNotDispatched(DispatchCampaign::class);
});
