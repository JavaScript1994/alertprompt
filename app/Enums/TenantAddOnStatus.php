<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantAddOnStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
