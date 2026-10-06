<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantPlan: string
{
    case Starter = 'starter';
    case Growth = 'growth';
    case Scale = 'scale';
    case Enterprise = 'enterprise';
}
