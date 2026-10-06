<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplateCategory: string
{
    case Marketing = 'marketing';
    case Utility = 'utility';
    case Authentication = 'authentication';

    public function requiresConsent(): bool
    {
        return $this === self::Marketing;
    }
}
