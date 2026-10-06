<?php

declare(strict_types=1);

namespace App\Enums;

enum Channel: string
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            Channel::WhatsApp => 'WhatsApp',
            Channel::Sms => 'SMS',
            Channel::Email => 'Email',
        };
    }
}
