<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled]);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Scheduled, self::Running, self::Cancelled]),
            self::Scheduled => in_array($next, [self::Running, self::Cancelled]),
            self::Running => in_array($next, [self::Paused, self::Completed, self::Cancelled]),
            self::Paused => in_array($next, [self::Running, self::Cancelled]),
            self::Completed, self::Cancelled => false,
        };
    }
}
