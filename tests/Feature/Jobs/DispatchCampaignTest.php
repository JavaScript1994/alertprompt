<?php

declare(strict_types=1);

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\TemplateCategory;
use App\Jobs\DispatchCampaign;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Tenant;
use App\Services\Channels\SendResult;
use App\Services\Channels\WhatsApp\WhatsAppTwilioDriver;
use Illuminate\Support\Facades\Bus;

afterEach(function () {
    Mockery::close();
});

it('sends only to recipients already enrolled for the campaign, not the whole tenant', function () {
    $tenant = Tenant::factory()->create();
    $template = Template::factory()->for($tenant)->create(['channel' => Channel::WhatsApp, 'category' => TemplateCategory::Utility]);
    $campaign = Campaign::factory()->for($tenant)->create([
        'template_id' => $template->id,
        'channel' => Channel::WhatsApp,
        'status' => CampaignStatus::Scheduled,
    ]);

    $enrolled = Contact::factory()->for($tenant)->create(['phone' => '+51911111111']);
    CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $enrolled->id,
        'status' => CampaignRecipientStatus::Pending,
    ]);

    // Contacto del mismo tenant que NUNCA se matriculó — no debe recibir nada.
    Contact::factory()->for($tenant)->create(['phone' => '+51922222222']);

    $driver = Mockery::mock(WhatsAppTwilioDriver::class);
    $driver->shouldReceive('send')->once()->andReturn(SendResult::success('SM1'));
    app()->instance(WhatsAppTwilioDriver::class, $driver);

    (new DispatchCampaign($campaign->id))->handle();

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(1)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Completed);
});

it('does not dispatch a batch when the campaign is not running', function () {
    Bus::fake();

    $tenant = Tenant::factory()->create();
    $template = Template::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->create([
        'template_id' => $template->id,
        'status' => CampaignStatus::Completed,
    ]);

    DispatchCampaign::dispatchNextBatchFor($campaign);

    Bus::assertNothingBatched();
});
