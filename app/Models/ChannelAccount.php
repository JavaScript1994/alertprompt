<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Enums\QualityRating;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Channels\SenderIdentity;
use Illuminate\Database\Eloquent\Model;

/**
 * Remitente propio de un tenant para un canal (su número de WhatsApp o SMS).
 *
 * @property Channel $channel
 * @property ChannelAccountStatus $status
 * @property ?array<string, string> $credentials
 */
class ChannelAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'channel', 'provider', 'display_name', 'sender', 'credentials',
        'status', 'quality_rating', 'messaging_tier', 'notes', 'activated_at',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => ChannelAccountStatus::class,
            'quality_rating' => QualityRating::class,
            'credentials' => 'encrypted:array',
            'activated_at' => 'datetime',
        ];
    }

    public function toSenderIdentity(): SenderIdentity
    {
        return new SenderIdentity(
            from: $this->sender,
            credentials: $this->credentials ?? [],
            accountId: $this->id,
        );
    }

    /** Qué credenciales hay cargadas, sin revelarlas (p. ej. "auth_token: ••••3f9a"). */
    public function credentialHints(): array
    {
        return collect($this->credentials ?? [])
            ->map(fn ($value) => is_string($value) && strlen($value) > 4 ? '••••'.substr($value, -4) : '••••')
            ->all();
    }
}
