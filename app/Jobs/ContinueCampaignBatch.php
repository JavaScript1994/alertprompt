<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AlertSeverity;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\Alerts;
use App\Services\CampaignPacer;
use Illuminate\Bus\Batch;

/**
 * Callback de Bus::batch()->finally() como clase invocable en vez de closure.
 *
 * Un closure ahí requiere que Laravel serialice su código fuente (vía
 * ReflectionClosure, que lee el archivo .php real con file_get_contents) para
 * poder reconstruirlo al ejecutar el callback — en bind mounts de Docker
 * sobre WSL2 esa lectura puede colgarse de forma intermitente, dejando el
 * job en estado "reserved" para siempre. Una clase invocable serializa como
 * un objeto común (sin leer ningún archivo), evitando el problema.
 */
class ContinueCampaignBatch
{
    public function __construct(public readonly int $campaignId) {}

    public function __invoke(Batch $batch): void
    {
        $campaign = Campaign::query()->find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $campaign->refresh();

        // Puede haberse pausado dentro del propio lote (p.ej. error de cuenta
        // del proveedor detectado por un SendMessageJob individual).
        if ($campaign->status !== CampaignStatus::Running) {
            return;
        }

        $counts = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) ($counts['sent'] ?? 0) + (int) ($counts['delivered'] ?? 0) + (int) ($counts['read'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);

        if (app(CampaignPacer::class)->shouldPause($sent, $failed)) {
            $campaign->transitionTo(CampaignStatus::Paused);

            $rate = $sent > 0 ? round($failed / $sent * 100, 1) : 0.0;
            app(Alerts::class)->raise(
                tenantId: $campaign->tenant_id,
                type: 'campaign.auto_paused',
                severity: AlertSeverity::Warning,
                title: "Campaña «{$campaign->name}» pausada automáticamente",
                message: "Los fallos llegaron al {$rate}% de lo enviado (umbral ".(config('campaigns.failure_threshold') * 100).'%). Revisa la base y la plantilla antes de reanudar.',
                data: ['sent' => $sent, 'failed' => $failed, 'failure_rate' => $rate],
                campaignId: $campaign->id,
            );

            return;
        }

        DispatchCampaign::dispatchNextBatchFor($campaign);
    }
}
