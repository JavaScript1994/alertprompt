<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Log;

/**
 * Registra alertas operativas. Si la misma señal sigue abierta (mismo
 * tenant, tipo y sujeto) no se duplica: se cuenta otra ocurrencia.
 * Se llama desde jobs sin tenant activo, así que todo es explícito.
 */
class Alerts
{
    /** @param  array<string, mixed>  $data */
    public function raise(
        int $tenantId,
        string $type,
        AlertSeverity $severity,
        string $title,
        string $message,
        array $data = [],
        ?int $campaignId = null,
        ?string $subjectKey = null,
    ): Alert {
        $fingerprint = implode(':', array_filter([$tenantId, $type, $campaignId ?? $subjectKey], fn ($part) => $part !== null));

        $open = Alert::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('fingerprint', $fingerprint)
            ->whereNull('resolved_at')
            ->first();

        if ($open !== null) {
            $open->forceFill([
                'occurrences' => $open->occurrences + 1,
                'severity' => $severity,
                'message' => $message,
                'data' => $data,
            ])->save();

            return $open;
        }

        Log::log($severity === AlertSeverity::Critical ? 'critical' : 'warning', $title, ['tenant_id' => $tenantId, 'type' => $type] + $data);

        return Alert::query()->create([
            'tenant_id' => $tenantId,
            'campaign_id' => $campaignId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'fingerprint' => $fingerprint,
        ]);
    }

    public function resolve(Alert $alert, ?int $userId): Alert
    {
        $alert->forceFill(['resolved_at' => now(), 'resolved_by' => $userId])->save();

        return $alert;
    }
}
