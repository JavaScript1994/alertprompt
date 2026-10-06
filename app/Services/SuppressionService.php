<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\SuppressionReason;
use App\Models\Suppression;

class SuppressionService
{
    public function isSuppressed(int $tenantId, Channel $channel, string $identifier): bool
    {
        return Suppression::query()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel->value)
            ->where('identifier', $identifier)
            ->exists();
    }

    public function suppress(int $tenantId, Channel $channel, string $identifier, SuppressionReason $reason): void
    {
        Suppression::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'channel' => $channel->value, 'identifier' => $identifier],
            ['reason' => $reason->value],
        );
    }
}
