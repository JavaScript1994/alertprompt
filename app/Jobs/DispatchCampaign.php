<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class DispatchCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $campaignId) {}

    public function handle(): void
    {
        $campaign = Campaign::query()->with('template')->findOrFail($this->campaignId);

        $campaign->transitionTo(CampaignStatus::Running);
        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::Running) {
            return;
        }

        // La audiencia (CampaignRecipient) ya se arma al crear la campaña,
        // vía CampaignAudienceService — acá solo se procesan los lotes.
        self::dispatchNextBatchFor($campaign);
    }

    /**
     * §6.1: lotes de tamaño fijo vía Bus::batch(), pacing por feedback en vez
     * de sleep fijo. Al terminar el lote, evalúa y decide si sigue o pausa.
     *
     * Estático y sin depender de $this: lo invoca tanto handle() como
     * ContinueCampaignBatch (el callback ->finally() del batch anterior).
     */
    public static function dispatchNextBatchFor(Campaign $campaign): void
    {
        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::Running) {
            return;
        }

        $recipientIds = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending)
            ->orderBy('id')
            ->limit((int) config('campaigns.batch_size'))
            ->pluck('id');

        if ($recipientIds->isEmpty()) {
            $campaign->transitionTo(CampaignStatus::Completed);

            return;
        }

        CampaignRecipient::query()->whereIn('id', $recipientIds)->update(['status' => CampaignRecipientStatus::Queued]);

        Bus::batch($recipientIds->map(fn (int $id) => new SendMessageJob($id))->all())
            ->name("campaign-{$campaign->id}-batch")
            ->finally(new ContinueCampaignBatch($campaign->id))
            ->dispatch();
    }
}
