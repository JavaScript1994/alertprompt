<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodType: string
{
    case Transfer = 'transfer';
    case Yape = 'yape';
    case Plin = 'plin';
    case Cash = 'cash';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            PaymentMethodType::Transfer => 'Transferencia',
            PaymentMethodType::Yape => 'Yape',
            PaymentMethodType::Plin => 'Plin',
            PaymentMethodType::Cash => 'Efectivo',
            PaymentMethodType::Card => 'Tarjeta',
        };
    }
}
