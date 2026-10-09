<?php

declare(strict_types=1);

namespace App\Enums;

/** Calidad del número según Meta (CLAUDE.md §2.2): RED tumba cuentas. */
enum QualityRating: string
{
    case Green = 'GREEN';
    case Yellow = 'YELLOW';
    case Red = 'RED';
    case Unknown = 'UNKNOWN';
}
