<?php

declare(strict_types=1);

use App\Enums\AlertSeverity;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Jobs\ContinueCampaignBatch;
use App\Jobs\SendMessageJob;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Scopes\TenantScope;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Alerts;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\SendResult;
use App\Services\Channels\WhatsApp\WhatsAppTwilioDriver;
use App\Services\SuppressionService;
use App\Services\TemplateRenderer;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;

// Usan rutas con acciones sensibles (reauth:*).
beforeEach(fn () => $this->reauthConfirmed = true);

afterEach(fn () => Mockery::close());

function openAlerts(): Builder
{
    return Alert::query()->withoutGlobalScope(TenantScope::class)->whereNull('resolved_at');
}

it('raises a warning when the pacer pauses a campaign', function () {
    $tenant = Tenant::factory()->create();
    $campaign = Campaign::factory()->for($tenant)->create(['status' => CampaignStatus::Running]);
    foreach ([CampaignRecipientStatus::Sent, CampaignRecipientStatus::Failed] as $status) {
        CampaignRecipient::factory()->create(['campaign_id' => $campaign->id, 'status' => $status, 'contact_id' => Contact::factory()->for($tenant)]);
    }

    (new ContinueCampaignBatch($campaign->id))(Mockery::mock(Batch::class));

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Paused);
    $alert = openAlerts()->where('type', 'campaign.auto_paused')->firstOrFail();
    expect($alert->tenant_id)->toBe($tenant->id)
        ->and($alert->campaign_id)->toBe($campaign->id)
        ->and($alert->severity)->toBe(AlertSeverity::Warning)
        ->and($alert->data['failure_rate'])->toEqual(100.0);
});

it('raises a critical alert when the provider blocks the account', function () {
    $tenant = Tenant::factory()->create();
    $template = Template::factory()->for($tenant)->create(['channel' => Channel::WhatsApp, 'body' => 'Hola']);
    $campaign = Campaign::factory()->for($tenant)->create(['template_id' => $template->id, 'channel' => Channel::WhatsApp, 'status' => CampaignStatus::Running]);
    $contact = Contact::factory()->for($tenant)->create(['phone' => '+51987654321']);
    Consent::factory()->for($contact)->create(['channel' => Channel::WhatsApp]);
    $recipient = CampaignRecipient::factory()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'status' => CampaignRecipientStatus::Queued]);

    $driver = Mockery::mock(WhatsAppTwilioDriver::class);
    $driver->shouldReceive('send')->once()->andReturn(SendResult::failure('131031', shouldHaltCampaign: true));
    app()->instance(WhatsAppTwilioDriver::class, $driver);

    app(SendMessageJob::class, ['campaignRecipientId' => $recipient->id])->handle(
        app(ChannelManager::class), app(SuppressionService::class), app(TemplateRenderer::class),
    );

    $alert = openAlerts()->where('type', 'account.blocked')->firstOrFail();
    expect($alert->severity)->toBe(AlertSeverity::Critical)->and($alert->data['error_code'])->toBe('131031');
});

it('groups repeated signals into one open alert', function () {
    $tenant = Tenant::factory()->create();
    $alerts = app(Alerts::class);

    $alerts->raise($tenant->id, 'campaign.auto_paused', AlertSeverity::Warning, 'X', 'uno', campaignId: null, subjectKey: 'k');
    $second = $alerts->raise($tenant->id, 'campaign.auto_paused', AlertSeverity::Warning, 'X', 'dos', subjectKey: 'k');

    expect(openAlerts()->count())->toBe(1)->and($second->occurrences)->toBe(2)->and($second->message)->toBe('dos');

    $alerts->resolve($second, null);
    $alerts->raise($tenant->id, 'campaign.auto_paused', AlertSeverity::Warning, 'X', 'tres', subjectKey: 'k');
    expect(openAlerts()->count())->toBe(1)->and(Alert::query()->withoutGlobalScope(TenantScope::class)->count())->toBe(2);
});

it('alerts when a WhatsApp number drops to red quality', function () {
    $client = Tenant::factory()->create();

    $this->actingAs(platformOwner())->putJson("/api/admin/clients/{$client->id}/channel-accounts/whatsapp", [
        'provider' => 'twilio', 'sender' => '+51900111222', 'status' => 'active', 'quality_rating' => 'RED',
    ])->assertSuccessful();

    $alert = openAlerts()->where('type', 'channel.quality_drop')->firstOrFail();
    expect($alert->tenant_id)->toBe($client->id)->and($alert->severity)->toBe(AlertSeverity::Critical);
});

it('lists open alerts of every client for the platform and resolves them', function () {
    $owner = platformOwner();
    $a = Tenant::factory()->create(['name' => 'Cliente A']);
    $b = Tenant::factory()->create(['name' => 'Cliente B']);
    app(Alerts::class)->raise($a->id, 'x', AlertSeverity::Warning, 'Aviso A', 'm');
    $critical = app(Alerts::class)->raise($b->id, 'y', AlertSeverity::Critical, 'Crítica B', 'm');

    $list = $this->actingAs($owner)->getJson('/api/admin/alerts')->assertOk();
    expect($list->json('data.0.title'))->toBe('Crítica B')
        ->and($list->json('data.0.tenant.name'))->toBe('Cliente B')
        ->and($list->json('data'))->toHaveCount(2);

    $this->actingAs($owner)->postJson("/api/admin/alerts/{$critical->id}/resolve")
        ->assertOk()
        ->assertJsonPath('data.resolved_by', $owner->name);

    expect($this->actingAs($owner)->getJson('/api/admin/alerts')->json('data'))->toHaveCount(1)
        ->and($this->actingAs($owner)->getJson('/api/admin/alerts?status=resolved')->json('data'))->toHaveCount(1);
});

it('shows a client only its own open alerts', function () {
    $user = User::factory()->create();
    app(Alerts::class)->raise($user->tenant_id, 'x', AlertSeverity::Warning, 'Mía', 'm');
    app(Alerts::class)->raise(Tenant::factory()->create()->id, 'x', AlertSeverity::Warning, 'Ajena', 'm');

    $response = $this->actingAs($user)->getJson('/api/alerts')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Mía')
        ->and($response->json('data.0'))->not->toHaveKey('tenant');
});

it('keeps clients out of the alerts inbox', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/admin/alerts')->assertForbidden();
});
