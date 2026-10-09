<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Trial = 'trial';
    case Suspended = 'suspended';

    public function canSignIn(): bool
    {
        return $this !== TenantStatus::Suspended;
    }
}
