<?php

declare(strict_types=1);

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Jobs\DispatchCampaign;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

it('lists only campaigns belonging to the authenticated tenant with live recipient stats', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->create();
    CampaignRecipient::factory()->for($campaign)->create(['status' => CampaignRecipientStatus::Sent]);
    CampaignRecipient::factory()->for($campaign)->create(['status' => CampaignRecipientStatus::Delivered]);
    CampaignRecipient::factory()->for($campaign)->create(['status' => CampaignRecipientStatus::Failed]);

    Campaign::factory()->for(Tenant::factory()->create())->create();

    $response = $this->actingAs($user)->getJson('/api/campaigns');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.stats.sent'))->toBe(1)
        ->and($response->json('data.0.stats.delivered'))->toBe(1)
        ->and($response->json('data.0.stats.failed'))->toBe(1)
        ->and($response->json('data.0.stats.total'))->toBe(3);
});

it('creates a campaign only from an approved template, enrolling only the selected contacts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $draftTemplate = Template::factory()->for($tenant)->create(['status' => TemplateStatus::Draft]);
    $contact = Contact::factory()->for($tenant)->create();
    $notSelected = Contact::factory()->for($tenant)->create();

    $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Campaña de prueba',
        'template_id' => $draftTemplate->id,
        'contact_ids' => [$contact->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['template_id']);

    $approvedTemplate = Template::factory()->for($tenant)->create([
        'status' => TemplateStatus::Approved,
        'channel' => Channel::Sms,
        'category' => TemplateCategory::Utility,
    ]);

    $response = $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Campaña de prueba',
        'template_id' => $approvedTemplate->id,
        'contact_ids' => [$contact->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', CampaignStatus::Draft->value)
        ->assertJsonPath('data.channel', $approvedTemplate->channel->value)
        ->assertJsonPath('data.stats.total', 1);

    $recipients = CampaignRecipient::query()->where('campaign_id', $response->json('data.id'))->pluck('contact_id');
    expect($recipients)->toEqual(collect([$contact->id]));
});

it('requires at least one contact', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $approvedTemplate = Template::factory()->for($tenant)->create(['status' => TemplateStatus::Approved]);

    $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Campaña de prueba',
        'template_id' => $approvedTemplate->id,
        'contact_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['contact_ids']);
});

it('does not allow using a template from another tenant', function () {
    $user = User::factory()->create();
    $otherTemplate = Template::factory()->create(['status' => TemplateStatus::Approved]);
    $contact = Contact::factory()->create();

    $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Campaña de prueba',
        'template_id' => $otherTemplate->id,
        'contact_ids' => [$contact->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['template_id']);
});

it('does not allow selecting a contact from another tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $approvedTemplate = Template::factory()->for($tenant)->create(['status' => TemplateStatus::Approved]);
    $otherTenantContact = Contact::factory()->create();

    $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Campaña de prueba',
        'template_id' => $approvedTemplate->id,
        'contact_ids' => [$otherTenantContact->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['contact_ids.0']);
});

it('creates a campaign scheduled for a future date instead of draft', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $template = Template::factory()->for($tenant)->create(['status' => TemplateStatus::Approved]);
    $contact = Contact::factory()->for($tenant)->create();
    $scheduledAt = now()->addDay();

    $response = $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Campaña programada',
        'template_id' => $template->id,
        'contact_ids' => [$contact->id],
        'scheduled_at' => $scheduledAt->toIso8601String(),
    ]);

    $response->assertCreated()->assertJsonPath('data.status', CampaignStatus::Scheduled->value);
});

it('rejects a scheduled_at in the past', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $template = Template::factory()->for($tenant)->create(['status' => TemplateStatus::Approved]);
    $contact = Contact::factory()->for($tenant)->create();

    $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Campaña programada',
        'template_id' => $template->id,
        'contact_ids' => [$contact->id],
        'scheduled_at' => now()->subHour()->toIso8601String(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['scheduled_at']);
});

it('dispatches a draft campaign', function () {
    Bus::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->create(['status' => CampaignStatus::Draft]);

    $this->actingAs($user)->postJson("/api/campaigns/{$campaign->id}/dispatch")->assertOk();

    Bus::assertDispatched(DispatchCampaign::class, fn ($job) => $job->campaignId === $campaign->id);
});

it('rejects dispatching a campaign that is already running', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->create(['status' => CampaignStatus::Running]);

    $this->actingAs($user)->postJson("/api/campaigns/{$campaign->id}/dispatch")->assertStatus(422);
});

it('returns 404 for a campaign from another tenant', function () {
    $user = User::factory()->create();
    $otherCampaign = Campaign::factory()->create();

    $this->actingAs($user)->getJson("/api/campaigns/{$otherCampaign->id}")->assertNotFound();
});

it('updates a draft campaign, replacing its audience', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $template = Template::factory()->for($tenant)->create(['status' => TemplateStatus::Approved]);
    $keptContact = Contact::factory()->for($tenant)->create();
    $droppedContact = Contact::factory()->for($tenant)->create();
    $newContact = Contact::factory()->for($tenant)->create();

    $campaign = Campaign::factory()->for($tenant)->for($template)->create(['status' => CampaignStatus::Draft]);
    CampaignRecipient::factory()->for($campaign)->for($keptContact)->create();
    CampaignRecipient::factory()->for($campaign)->for($droppedContact)->create();

    $response = $this->actingAs($user)->putJson("/api/campaigns/{$campaign->id}", [
        'name' => 'Campaña editada',
        'template_id' => $template->id,
        'contact_ids' => [$keptContact->id, $newContact->id],
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Campaña editada')->assertJsonPath('data.stats.total', 2);

    $recipients = CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('contact_id');
    expect($recipients->sort()->values())->toEqual(collect([$keptContact->id, $newContact->id])->sort()->values());
});

it('rejects editing a campaign that already started sending', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $template = Template::factory()->for($tenant)->create(['status' => TemplateStatus::Approved]);
    $contact = Contact::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->for($template)->create(['status' => CampaignStatus::Running]);

    $this->actingAs($user)->putJson("/api/campaigns/{$campaign->id}", [
        'name' => 'Campaña editada',
        'template_id' => $template->id,
        'contact_ids' => [$contact->id],
    ])->assertStatus(422);
});

it('deletes a draft campaign', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->create(['status' => CampaignStatus::Draft]);

    $this->actingAs($user)->deleteJson("/api/campaigns/{$campaign->id}")->assertNoContent();

    expect(Campaign::query()->find($campaign->id))->toBeNull();
});

it('deletes a scheduled campaign', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->create(['status' => CampaignStatus::Scheduled]);

    $this->actingAs($user)->deleteJson("/api/campaigns/{$campaign->id}")->assertNoContent();

    expect(Campaign::query()->find($campaign->id))->toBeNull();
});

it('rejects deleting a campaign that already started sending', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->for($tenant)->create(['status' => CampaignStatus::Running]);

    $this->actingAs($user)->deleteJson("/api/campaigns/{$campaign->id}")->assertStatus(422);

    expect(Campaign::query()->find($campaign->id))->not->toBeNull();
});

it('returns 404 when deleting a campaign from another tenant', function () {
    $user = User::factory()->create();
    $otherCampaign = Campaign::factory()->create(['status' => CampaignStatus::Draft]);

    $this->actingAs($user)->deleteJson("/api/campaigns/{$otherCampaign->id}")->assertNotFound();
});
