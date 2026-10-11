<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\TemplateCategory;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Modules\TenantModules;

// Usan rutas con acciones sensibles (reauth:*).
beforeEach(fn () => $this->reauthConfirmed = true);

/** @param  array<string, int>  $statuses */
function seedCampaign(Tenant $tenant, Channel $channel, TemplateCategory $category, array $statuses, string $sentAt = 'now'): Campaign
{
    $template = Template::factory()->for($tenant)->create(['channel' => $channel, 'category' => $category]);
    $campaign = Campaign::factory()->for($tenant)->create(['template_id' => $template->id, 'channel' => $channel]);

    foreach ($statuses as $status => $count) {
        for ($i = 0; $i < $count; $i++) {
            CampaignRecipient::factory()->create([
                'campaign_id' => $campaign->id,
                'contact_id' => Contact::factory()->for($tenant),
                'status' => $status,
                'sent_at' => in_array($status, ['sent', 'delivered', 'read'], true) ? $sentAt : null,
            ]);
        }
    }

    return $campaign;
}

it('summarises a client deliveries by status, channel and category', function () {
    $user = User::factory()->create();
    seedCampaign($user->tenant, Channel::WhatsApp, TemplateCategory::Marketing, ['delivered' => 3, 'read' => 1, 'failed' => 1]);
    seedCampaign($user->tenant, Channel::Sms, TemplateCategory::Utility, ['sent' => 2, 'skipped' => 1, 'pending' => 5]);
    seedCampaign(Tenant::factory()->create(), Channel::Sms, TemplateCategory::Utility, ['delivered' => 9]);

    $data = $this->actingAs($user)->getJson('/api/reports')->assertOk()->json('data');

    expect($data['totals'])->toMatchArray([
        'attempted' => 8, 'sent' => 6, 'delivered' => 4, 'read' => 1, 'failed' => 1, 'skipped' => 1, 'delivery_rate' => 66.7,
    ]);
    $byChannel = collect($data['by_channel'])->keyBy('channel');
    expect($byChannel['whatsapp']['delivered'])->toBe(4)->and($byChannel['sms']['sent'])->toBe(2);
    expect(collect($data['by_category'])->keyBy('category')['marketing']['delivered'])->toBe(4);
    expect($data['daily'])->toHaveCount(1)->and($data['campaigns'])->toHaveCount(2);
});

it('filters by date range', function () {
    $user = User::factory()->create();
    seedCampaign($user->tenant, Channel::Sms, TemplateCategory::Utility, ['sent' => 2], sentAt: now()->subDays(60)->toDateTimeString());
    seedCampaign($user->tenant, Channel::Sms, TemplateCategory::Utility, ['sent' => 1]);

    expect($this->actingAs($user)->getJson('/api/reports')->json('data.totals.sent'))->toBe(1);

    $from = now()->subDays(90)->toDateString();
    expect($this->actingAs($user)->getJson("/api/reports?from={$from}")->json('data.totals.sent'))->toBe(3);
});

it('rejects ranges longer than a year', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/reports?from=2024-01-01&to=2026-01-01')
        ->assertStatus(422);
});

it('requires the reports module', function () {
    $user = User::factory()->create();
    app(TenantModules::class)->sync($user->tenant, ['sms']);

    $this->actingAs($user)->getJson('/api/reports')->assertForbidden();
});

it('exports the campaigns of the period as CSV', function () {
    $user = User::factory()->create();
    seedCampaign($user->tenant, Channel::Sms, TemplateCategory::Utility, ['delivered' => 2]);

    $response = $this->actingAs($user)->get('/api/reports/export')->assertOk();

    $csv = $response->streamedContent();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toContain('Campaña,Canal,Categoría')
        ->and($csv)->toContain(',sms,utility,');
});

it('does not let a read-only user export', function () {
    $this->actingAs(User::factory()->withRole('client-viewer')->create())->get('/api/reports/export')->assertForbidden();
});

it('gives the platform totals per client', function () {
    $a = Tenant::factory()->create(['name' => 'Alfa']);
    $b = Tenant::factory()->create(['name' => 'Beta']);
    seedCampaign($a, Channel::Sms, TemplateCategory::Utility, ['delivered' => 5]);
    seedCampaign($b, Channel::Sms, TemplateCategory::Utility, ['delivered' => 2, 'failed' => 1]);

    $data = $this->actingAs(platformOwner())->getJson('/api/admin/reports')->assertOk()->json('data');

    expect($data['totals']['delivered'])->toBe(7)
        ->and($data['by_tenant'][0]['tenant_name'])->toBe('Alfa')
        ->and($data['by_tenant'][1]['failed'])->toBe(1)
        ->and($data['clients']['active'])->toBeGreaterThanOrEqual(2);

    $csv = $this->actingAs(platformOwner())->get('/api/admin/reports/export')->streamedContent();
    expect($csv)->toContain('Cliente,Campaña')->and($csv)->toContain('Alfa');
});
