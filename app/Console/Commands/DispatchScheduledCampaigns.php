<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AlertSeverity;
use App\Enums\CampaignStatus;
use App\Jobs\DispatchCampaign;
use App\Models\Campaign;
use App\Services\Alerts;
use App\Services\Channels\ChannelManager;
use App\Services\Modules\TenantModules;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-scheduled';

    protected $description = 'Dispara las campañas programadas cuya fecha/hora ya llegó.';

    public function handle(TenantModules $modules, ChannelManager $channels, Alerts $alerts): int
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
                $alerts->raise(
                    tenantId: $campaign->tenant_id,
                    type: 'campaign.schedule_blocked',
                    severity: AlertSeverity::Warning,
                    title: 'Campaña programada sin enviar',
                    message: 'El plan ya no incluye el canal o los envíos programados. La campaña sigue programada.',
                    data: ['reason' => 'module_disabled'],
                    campaignId: $campaign->id,
                );

                continue;
            }

            if (! $channels->canSend($campaign->tenant_id, $campaign->channel)) {
                $alerts->raise(
                    tenantId: $campaign->tenant_id,
                    type: 'campaign.schedule_blocked',
                    severity: AlertSeverity::Warning,
                    title: 'Campaña programada sin enviar',
                    message: "No hay un número de {$campaign->channel->label()} activo para este cliente. La campaña sigue programada.",
                    data: ['reason' => 'no_sender_account'],
                    campaignId: $campaign->id,
                );

                continue;
            }

            DispatchCampaign::dispatch($campaign->id);
            $dispatched++;
        }

        $this->info("Campañas programadas disparadas: {$dispatched}");

        return self::SUCCESS;
    }
}
