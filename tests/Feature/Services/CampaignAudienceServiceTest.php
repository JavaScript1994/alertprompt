<?php

declare(strict_types=1);

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\SkipReason;
use App\Enums\TemplateCategory;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Tenant;
use App\Services\CampaignAudienceService;

beforeEach(function () {
    $this->service = new CampaignAudienceService;
});

it('enrolls only the selected contacts, skipping those without consent or a valid identifier', function () {
    $tenant = Tenant::factory()->create();
    $template = Template::factory()->for($tenant)->create([
        'channel' => Channel::WhatsApp,
        'category' => TemplateCategory::Marketing,
    ]);
    $campaign = Campaign::factory()->for($tenant)->create([
        'template_id' => $template->id,
        'channel' => Channel::WhatsApp,
        'status' => CampaignStatus::Draft,
    ]);

    $withConsent = Contact::factory()->for($tenant)->create(['phone' => '+51911111111']);
    Consent::factory()->for($withConsent)->create(['channel' => Channel::WhatsApp]);

    $withoutConsent = Contact::factory()->for($tenant)->create(['phone' => '+51922222222']);
    $withoutPhone = Contact::factory()->for($tenant)->create(['phone' => null, 'email' => 'x@example.com']);
    $notSelected = Contact::factory()->for($tenant)->create(['phone' => '+51933333333']);
    Consent::factory()->for($notSelected)->create(['channel' => Channel::WhatsApp]);

    $this->service->enroll($campaign, [$withConsent->id, $withoutConsent->id, $withoutPhone->id]);

    $recipients = CampaignRecipient::query()->where('campaign_id', $campaign->id)->get()->keyBy('contact_id');

    expect($recipients)->toHaveCount(3)
        ->and($recipients[$withConsent->id]->status)->toBe(CampaignRecipientStatus::Pending)
        ->and($recipients[$withoutConsent->id]->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($recipients[$withoutConsent->id]->skip_reason)->toBe(SkipReason::NoConsent)
        ->and($recipients[$withoutPhone->id]->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($recipients[$withoutPhone->id]->skip_reason)->toBe(SkipReason::InvalidNumber)
        ->and($recipients->has($notSelected->id))->toBeFalse();
});

it('does not enroll a contact twice when called again for the same campaign', function () {
    $tenant = Tenant::factory()->create();
    $template = Template::factory()->for($tenant)->create(['channel' => Channel::WhatsApp, 'category' => TemplateCategory::Utility]);
    $campaign = Campaign::factory()->for($tenant)->create(['template_id' => $template->id, 'channel' => Channel::WhatsApp]);
    $contact = Contact::factory()->for($tenant)->create(['phone' => '+51911111111']);

    $this->service->enroll($campaign, [$contact->id]);
    $this->service->enroll($campaign, [$contact->id]);

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
});

it('does not enroll contacts from another tenant', function () {
    $tenant = Tenant::factory()->create();
    $template = Template::factory()->for($tenant)->create(['channel' => Channel::WhatsApp, 'category' => TemplateCategory::Utility]);
    $campaign = Campaign::factory()->for($tenant)->create(['template_id' => $template->id, 'channel' => Channel::WhatsApp]);
    $otherTenantContact = Contact::factory()->create(['phone' => '+51911111111']);

    $this->service->enroll($campaign, [$otherTenantContact->id]);

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(0);
});
