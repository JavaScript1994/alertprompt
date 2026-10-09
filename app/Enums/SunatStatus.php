<?php

declare(strict_types=1);

namespace App\Enums;

/** Estado ante SUNAT. Solo "accepted" es un comprobante electrónico válido. */
enum SunatStatus: string
{
    case NotSent = 'not_sent';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
