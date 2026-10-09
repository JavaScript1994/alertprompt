<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A qué panel pertenece un rol. Un rol `platform` solo se asigna a usuarios
 * del tenant de la plataforma (AlertPrompt); uno `client`, a usuarios de
 * empresas o personas naturales.
 */
enum RoleScope: string
{
    case Platform = 'platform';
    case Client = 'client';
}
