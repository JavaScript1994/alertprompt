<?php

declare(strict_types=1);

namespace App\Enums;

enum AddOnStatus: string
{
    case Available = 'available';
    case ComingSoon = 'coming_soon';
}
