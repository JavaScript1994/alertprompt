<?php

declare(strict_types=1);

use App\Enums\CampaignRecipientStatus;
use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Enums\TemplateCategory;
use App\Jobs\ProcessMessageStatusUpdate;
use App\Jobs\SendMessageJob;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SendResult;
use App\Services\Channels\Sms\SmsTwilioDriver;
use App\Services\SuppressionService;
use App\Services\TemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Twilio\Security\RequestValidator;

afterEach(fn () => Mockery::close());

function smsRecipientFor(Tenant $tenant): CampaignRecipient
{
    $template = Template::factory()->for($tenant)->create([
        'channel' => Channel::Sms, 'category' => TemplateCategory::Utility, 'body' => 'Hola',
    ]);
    $campaign = Campaign::factory()->for($tenant)->create(['template_id' => $template->id, 'channel' => Channel::Sms]);
    $contact = Contact::factory()->for($tenant)->create(['phone' => '+51987654321']);

    return CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => CampaignRecipientStatus::Queued,
    ]);
}

function activeSmsAccount(Tenant $tenant, array $overrides = []): ChannelAccount
{
    return ChannelAccount::query()->create([
        'tenant_id' => $tenant->id,
        'channel' => Channel::Sms,
        'provider' => 'twilio',
        'sender' => '+51900111222',
        'credentials' => ['account_sid' => 'ACcliente', 'auth_token' => 'token-del-cliente'],
        'status' => ChannelAccountStatus::Active,
        ...$overrides,
    ]);
}

function runSendJob(CampaignRecipient $recipient): void
{
    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class),
        app(SuppressionService::class),
        app(TemplateRenderer::class),
    );
}

it('sends with the client own number and asks for status on its webhook', function () {
    $tenant = Tenant::factory()->create();
    $account = activeSmsAccount($tenant);
    $recipient = smsRecipientFor($tenant);

    $sent = null;
    $driver = Mockery::mock(SmsTwilioDriver::class);
    $driver->shouldReceive('send')->once()->andReturnUsing(function (OutboundMessage $message) use (&$sent) {
        $sent = $message;

        return SendResult::success('SM1');
    });
    app()->instance(SmsTwilioDriver::class, $driver);

    runSendJob($recipient);

    expect($sent->sender->from)->toBe('+51900111222')
        ->and($sent->sender->credential('auth_token'))->toBe('token-del-cliente')
        ->and($sent->statusCallbackUrl)->toBe(route('webhooks.account', ['channel' => 'sms', 'account' => $account->id]))
        ->and($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Sent);
});

it('uses the shared sender when the client has no active number', function () {
    $tenant = Tenant::factory()->create();
    activeSmsAccount($tenant, ['status' => ChannelAccountStatus::Pending]);
    $recipient = smsRecipientFor($tenant);

    $sent = null;
    $driver = Mockery::mock(SmsTwilioDriver::class);
    $driver->shouldReceive('send')->once()->andReturnUsing(function (OutboundMessage $message) use (&$sent) {
        $sent = $message;

        return SendResult::success('SM2');
    });
    app()->instance(SmsTwilioDriver::class, $driver);

    runSendJob($recipient);

    expect($sent->sender)->toBeNull()->and($sent->statusCallbackUrl)->toBeNull();
});

it('fails without retry when there is no number and the shared sender is off', function () {
    config(['channels.shared_sender' => false]);
    $recipient = smsRecipientFor(Tenant::factory()->create());

    $driver = Mockery::mock(SmsTwilioDriver::class);
    $driver->shouldNotReceive('send');
    app()->instance(SmsTwilioDriver::class, $driver);

    runSendJob($recipient);

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Failed)
        ->and($recipient->fresh()->error_code)->toBe('no_sender_account');
});

it('refuses to dispatch without a number when the shared sender is off', function () {
    Queue::fake();
    config(['channels.shared_sender' => false]);
    $user = User::factory()->create();
    $campaign = Campaign::factory()->for($user->tenant)->create(['channel' => Channel::Sms, 'status' => 'draft']);

    $this->actingAs($user)->postJson("/api/campaigns/{$campaign->id}/dispatch")
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Conecta tu número'));
});

it('always allows email through the platform sender', function () {
    config(['channels.shared_sender' => false]);

    expect(app(ChannelManager::class)->canSend(Tenant::factory()->create()->id, Channel::Email))->toBeTrue();
});

it('verifies account webhooks with that account token', function () {
    Queue::fake();
    $account = activeSmsAccount(Tenant::factory()->create());
    $url = route('webhooks.account', ['channel' => 'sms', 'account' => $account->id]);
    $params = ['MessageSid' => 'SM9', 'MessageStatus' => 'delivered'];

    $good = (new RequestValidator('token-del-cliente'))->computeSignature($url, $params);
    $this->withHeader('X-Twilio-Signature', $good)->post($url, $params)->assertNoContent();
    Queue::assertPushed(ProcessMessageStatusUpdate::class);

    $wrong = (new RequestValidator('otro-token'))->computeSignature($url, $params);
    $this->withHeader('X-Twilio-Signature', $wrong)->post($url, $params)->assertForbidden();
});

it('lets the client request a number, which stays pending', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/channel-accounts/request', [
        'channel' => 'whatsapp', 'sender' => '+51 987 654 321', 'display_name' => 'Andina',
    ])->assertOk();

    $whatsapp = collect($response->json('data'))->firstWhere('channel', 'whatsapp');
    expect($whatsapp['account']['sender'])->toBe('+51987654321')
        ->and($whatsapp['account']['status'])->toBe('pending')
        ->and($whatsapp['account'])->not->toHaveKey('credential_hints')
        ->and($whatsapp['can_send'])->toBeTrue();
});

it('puts an active account back to pending when the client changes the number', function () {
    $user = User::factory()->create();
    activeSmsAccount($user->tenant);

    $this->actingAs($user)->postJson('/api/channel-accounts/request', ['channel' => 'sms', 'sender' => '+51911222333'])->assertOk();

    expect(ChannelAccount::query()->forTenant($user->tenant_id)->first()->status)->toBe(ChannelAccountStatus::Pending);
});

it('rejects numbers that are not E.164', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/channel-accounts/request', [
        'channel' => 'sms', 'sender' => '987654321',
    ])->assertUnprocessable()->assertJsonValidationErrors('sender');
});

it('lets the platform configure and activate an account, storing credentials encrypted', function () {
    $client = Tenant::factory()->create();

    $response = $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$client->id}/channel-accounts/whatsapp", [
        'provider' => 'twilio',
        'sender' => '+51900111222',
        'status' => 'active',
        'quality_rating' => 'GREEN',
        'messaging_tier' => 'TIER_1K',
        'credentials' => ['account_sid' => 'ACsub123456', 'auth_token' => 'secreto987654'],
    ])->assertSuccessful();

    $response->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.credential_hints.auth_token', '••••7654')
        ->assertJsonMissingPath('data.credentials');

    $raw = DB::table('channel_accounts')->where('tenant_id', $client->id)->value('credentials');
    expect($raw)->not->toContain('secreto987654');

    $log = AuditLog::query()->where('tenant_id', $client->id)->where('action', 'channel_account.configured')->firstOrFail();
    expect(json_encode($log->metadata))->not->toContain('secreto987654');
});

it('keeps stored credentials when the platform saves without new ones', function () {
    $client = Tenant::factory()->create();
    activeSmsAccount($client);

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$client->id}/channel-accounts/sms", [
        'provider' => 'twilio', 'sender' => '+51900111222', 'status' => 'disabled',
    ])->assertOk();

    $account = ChannelAccount::query()->forTenant($client->id)->first();
    expect($account->credentials['auth_token'])->toBe('token-del-cliente')
        ->and($account->status)->toBe(ChannelAccountStatus::Disabled);
});

it('only accepts providers configured for the channel', function () {
    $client = Tenant::factory()->create();

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$client->id}/channel-accounts/sms", [
        'provider' => 'cloud', 'sender' => '+51900111222', 'status' => 'active',
    ])->assertUnprocessable()->assertJsonValidationErrors('provider');
});

it('keeps clients away from configuring providers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson("/api/admin/clients/{$user->tenant_id}/channel-accounts/sms", [
        'provider' => 'twilio', 'sender' => '+51900111222', 'status' => 'active',
    ])->assertForbidden();
});
