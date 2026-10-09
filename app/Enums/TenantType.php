<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantType: string
{
    case Company = 'company';
    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            TenantType::Company => 'Empresa',
            TenantType::Individual => 'Persona natural',
        };
    }
}
