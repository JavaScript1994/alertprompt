<?php

declare(strict_types=1);

namespace App\Enums;

enum SkipReason: string
{
    case NoConsent = 'no_consent';
    case Suppressed = 'suppressed';
    case InvalidNumber = 'invalid_number';
    case InvalidEmail = 'invalid_email';
    case DuplicateRecipient = 'duplicate_recipient';
}
