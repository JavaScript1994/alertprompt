<?php

declare(strict_types=1);

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\SkipReason;
use App\Enums\TemplateCategory;
use App\Jobs\SendMessageJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Suppression;
use App\Models\Template;
use App\Models\Tenant;
use App\Services\Channels\ChannelDriver;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\SendResult;
use App\Services\Channels\WhatsApp\WhatsAppTwilioDriver;
use App\Services\SuppressionService;
use App\Services\TemplateRenderer;

afterEach(function () {
    Mockery::close();
});

function buildRecipient(Tenant $tenant, array $contactOverrides = [], array $templateOverrides = []): CampaignRecipient
{
    $template = Template::factory()->for($tenant)->create(array_merge([
        'channel' => Channel::WhatsApp,
        'category' => TemplateCategory::Marketing,
        'body' => 'Hola {{nombre}}.',
    ], $templateOverrides));

    $contact = Contact::factory()->for($tenant)->create(array_merge([
        'phone' => '+51987654321',
        'attributes' => ['nombre' => 'Carlos'],
    ], $contactOverrides));

    $campaign = Campaign::factory()->for($tenant)->create([
        'template_id' => $template->id,
        'channel' => Channel::WhatsApp,
        'status' => CampaignStatus::Running,
    ]);

    return CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => CampaignRecipientStatus::Queued,
    ]);
}

function bindMockDriver(SendResult $result): void
{
    $driver = Mockery::mock(WhatsAppTwilioDriver::class);
    $driver->shouldReceive('send')->once()->andReturn($result);
    app()->instance(WhatsAppTwilioDriver::class, $driver);
}

it('marks a recipient as sent on success', function () {
    $tenant = Tenant::factory()->create();
    $recipient = buildRecipient($tenant);
    Consent::factory()->for($recipient->contact)->create(['channel' => Channel::WhatsApp]);

    bindMockDriver(SendResult::success('SM123', 'queued'));

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );

    $recipient->refresh();
    expect($recipient->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($recipient->provider_message_id)->toBe('SM123');
});

it('skips without sending when the contact has no active consent', function () {
    $tenant = Tenant::factory()->create();
    $recipient = buildRecipient($tenant); // sin Consent creado

    // Ningún driver debe resolverse ni enviarse nada.
    app()->instance(WhatsAppTwilioDriver::class, Mockery::mock(ChannelDriver::class));

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );

    $recipient->refresh();
    expect($recipient->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($recipient->skip_reason)->toBe(SkipReason::NoConsent);
});

it('skips without sending when the identifier is suppressed', function () {
    $tenant = Tenant::factory()->create();
    $recipient = buildRecipient($tenant);
    Consent::factory()->for($recipient->contact)->create(['channel' => Channel::WhatsApp]);
    Suppression::factory()->for($tenant)->create([
        'channel' => Channel::WhatsApp,
        'identifier' => $recipient->contact->phone,
    ]);

    app()->instance(WhatsAppTwilioDriver::class, Mockery::mock(ChannelDriver::class));

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );

    $recipient->refresh();
    expect($recipient->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($recipient->skip_reason)->toBe(SkipReason::Suppressed);
});

it('suppresses the identifier when the driver reports an invalid number', function () {
    $tenant = Tenant::factory()->create();
    $recipient = buildRecipient($tenant);
    Consent::factory()->for($recipient->contact)->create(['channel' => Channel::WhatsApp]);

    bindMockDriver(SendResult::failure('21211', shouldSuppress: true));

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );

    $recipient->refresh();
    expect($recipient->status)->toBe(CampaignRecipientStatus::Failed)
        ->and($recipient->error_code)->toBe('21211');

    $this->assertDatabaseHas('suppressions', [
        'tenant_id' => $tenant->id,
        'channel' => Channel::WhatsApp->value,
        'identifier' => $recipient->contact->phone,
    ]);
});

it('pauses the campaign when the driver reports an account-level error', function () {
    $tenant = Tenant::factory()->create();
    $recipient = buildRecipient($tenant);
    Consent::factory()->for($recipient->contact)->create(['channel' => Channel::WhatsApp]);

    bindMockDriver(SendResult::failure('131031', shouldHaltCampaign: true));

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );

    expect($recipient->campaign->fresh()->status)->toBe(CampaignStatus::Paused);
});

it('marks a retryable failure as failed without throwing outside a queue worker', function () {
    $tenant = Tenant::factory()->create();
    $recipient = buildRecipient($tenant);
    Consent::factory()->for($recipient->contact)->create(['channel' => Channel::WhatsApp]);

    bindMockDriver(SendResult::failure('20500', shouldRetry: true));

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Failed);
});

it('does not resend a recipient already in a terminal state', function () {
    $tenant = Tenant::factory()->create();
    $recipient = buildRecipient($tenant);
    $recipient->update(['status' => CampaignRecipientStatus::Delivered]);

    // Si el driver se llamara, este mock fallaría al no tener expectativas.
    app()->instance(WhatsAppTwilioDriver::class, Mockery::mock(ChannelDriver::class));

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Delivered);
});
