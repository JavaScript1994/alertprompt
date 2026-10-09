<?php

declare(strict_types=1);

namespace App\Enums;

enum MembershipStatus: string
{
    /** Empieza en el futuro. */
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
