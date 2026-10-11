<?php

declare(strict_types=1);

namespace App\Enums;

enum EmailOtpPurpose: string
{
    /** Verificar un correo de respaldo nuevo. */
    case Setup = 'setup';
    /** Entrar sin el dispositivo de autenticación. */
    case Login = 'login';
}
