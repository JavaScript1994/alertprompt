<?php

declare(strict_types=1);

use App\Enums\CampaignRecipientStatus;
use App\Enums\Channel;
use App\Enums\SuppressionReason;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MessageEvent;
use App\Models\Template;
use App\Models\Tenant;
use App\Services\Channels\Email\EmailSendGridDriver;
use App\Services\Channels\MessageStatusUpdate;
use Twilio\Security\RequestValidator;

beforeEach(function () {
    config(['services.twilio.token' => 'test-auth-token']);
});

function whatsappWebhookRequest(array $params): array
{
    $url = route('webhooks.handle', ['channel' => 'whatsapp']);
    $signature = (new RequestValidator('test-auth-token'))->computeSignature($url, $params);

    return [$url, $signature];
}

function makeRecipientFor(Channel $channel, string $providerMessageId, ?Contact $contact = null): CampaignRecipient
{
    $tenant = Tenant::factory()->create();
    $template = Template::factory()->for($tenant)->create(['channel' => $channel]);
    $campaign = Campaign::factory()->for($tenant)->create(['template_id' => $template->id, 'channel' => $channel]);
    $contact ??= Contact::factory()->for($tenant)->create();

    return CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => CampaignRecipientStatus::Sent,
        'provider_message_id' => $providerMessageId,
    ]);
}

it('rejects a webhook without a valid signature', function () {
    $this->postJson('/api/webhooks/whatsapp', ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'])
        ->assertForbidden();
});

it('returns 404 for an unknown channel', function () {
    [$url, $signature] = whatsappWebhookRequest([]);

    $this->withHeaders(['X-Twilio-Signature' => $signature])
        ->postJson('/api/webhooks/carrier-pigeon', [])
        ->assertNotFound();
});

it('updates the recipient status on a valid delivered event', function () {
    $recipient = makeRecipientFor(Channel::WhatsApp, 'SM123');
    $params = ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'];
    [$url, $signature] = whatsappWebhookRequest($params);

    $this->withHeaders(['X-Twilio-Signature' => $signature])
        ->postJson('/api/webhooks/whatsapp', $params)
        ->assertNoContent();

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Delivered);
    $this->assertDatabaseHas('message_events', [
        'campaign_recipient_id' => $recipient->id,
        'event' => 'delivered',
    ]);
});

it('is idempotent when the same event arrives twice', function () {
    $recipient = makeRecipientFor(Channel::WhatsApp, 'SM123');
    $params = ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'];
    [$url, $signature] = whatsappWebhookRequest($params);

    $this->withHeaders(['X-Twilio-Signature' => $signature])->postJson('/api/webhooks/whatsapp', $params);
    $this->withHeaders(['X-Twilio-Signature' => $signature])->postJson('/api/webhooks/whatsapp', $params);

    expect(MessageEvent::query()->where('campaign_recipient_id', $recipient->id)->count())->toBe(1);
});

it('does not crash on an unknown provider_message_id', function () {
    $params = ['MessageSid' => 'SM-DOES-NOT-EXIST', 'MessageStatus' => 'delivered'];
    [$url, $signature] = whatsappWebhookRequest($params);

    $this->withHeaders(['X-Twilio-Signature' => $signature])
        ->postJson('/api/webhooks/whatsapp', $params)
        ->assertNoContent();

    expect(MessageEvent::query()->count())->toBe(0);
});

it('rejects an email webhook when no public key is configured to verify it', function () {
    makeRecipientFor(Channel::Email, 'sg-msg-1', Contact::factory()->create(['email' => 'bounced@example.com']));

    $event = ['sg_message_id' => 'sg-msg-1.filter001', 'event' => 'bounce'];

    $this->postJson('/api/webhooks/email', [$event])->assertForbidden();
});

it('accepts an email webhook when signature verification is satisfied by a stubbed driver', function () {
    $contact = Contact::factory()->create(['email' => 'bounced@example.com']);
    $recipient = makeRecipientFor(Channel::Email, 'sg-msg-1', $contact);

    $driver = Mockery::mock(EmailSendGridDriver::class);
    $driver->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
    $driver->shouldReceive('parseWebhook')->once()->andReturn([
        new MessageStatusUpdate('sg-msg-1', 'bounce', ['reason' => 'mailbox full']),
    ]);
    app()->instance(EmailSendGridDriver::class, $driver);

    $this->postJson('/api/webhooks/email', [['sg_message_id' => 'sg-msg-1.filter001', 'event' => 'bounce']])
        ->assertNoContent();

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Failed);
    $this->assertDatabaseHas('suppressions', [
        'identifier' => 'bounced@example.com',
        'channel' => Channel::Email->value,
        'reason' => SuppressionReason::HardBounce->value,
    ]);
});

afterEach(function () {
    Mockery::close();
});
