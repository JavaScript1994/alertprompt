<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelAccountStatus: string
{
    /** El cliente pidió conectar el número; falta configurarlo en el proveedor. */
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
}
