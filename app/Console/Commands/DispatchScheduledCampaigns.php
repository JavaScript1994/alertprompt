<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Jobs\DispatchCampaign;
use App\Models\Campaign;
use App\Services\Modules\TenantModules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-scheduled';

    protected $description = 'Dispara las campañas programadas cuya fecha/hora ya llegó.';

    public function handle(TenantModules $modules): int
    {
        $due = Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get(['id', 'tenant_id', 'channel']);

        $dispatched = 0;

        foreach ($due as $campaign) {
            // Si el cliente perdió el módulo del canal o el de programación
            // desde que la agendó, no se envía: queda programada y se avisa.
            if (! $modules->channelEnabled($campaign->channel, $campaign->tenant_id) || ! $modules->isEnabled('scheduling', $campaign->tenant_id)) {
                Log::warning('Campaña programada no disparada: módulo desactivado.', ['campaign_id' => $campaign->id]);

                continue;
            }

            DispatchCampaign::dispatch($campaign->id);
            $dispatched++;
        }

        $this->info("Campañas programadas disparadas: {$dispatched}");

        return self::SUCCESS;
    }
}
