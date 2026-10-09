<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Señal que requiere atención (campaña pausada, cuenta bloqueada, calidad
 * baja…). La ve el cliente en su dashboard y la plataforma en Alertas.
 */
class Alert extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'campaign_id', 'type', 'severity', 'title', 'message', 'data',
        'fingerprint', 'occurrences', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'data' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withoutGlobalScopes();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withoutGlobalScopes();
    }
}
