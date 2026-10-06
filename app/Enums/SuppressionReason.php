<?php

declare(strict_types=1);

namespace App\Enums;

enum SuppressionReason: string
{
    case Unsubscribed = 'unsubscribed';
    case HardBounce = 'hard_bounce';
    case SpamComplaint = 'spam_complaint';
    case InvalidNumber = 'invalid_number';
    case ProviderError = 'provider_error';
    case ManualBlock = 'manual_block';
}
