<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Jobs\DispatchCampaign;
use App\Models\Campaign;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-scheduled';

    protected $description = 'Dispara las campañas programadas cuya fecha/hora ya llegó.';

    public function handle(): int
    {
        $due = Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get(['id']);

        foreach ($due as $campaign) {
            DispatchCampaign::dispatch($campaign->id);
        }

        $this->info("Campañas programadas disparadas: {$due->count()}");

        return self::SUCCESS;
    }
}
