<?php

declare(strict_types=1);

namespace App\Enums;

enum AnnouncementAudience: string
{
    case All = 'all';
    /** Clientes de ciertos planes (announcements.plan_keys). */
    case Plans = 'plans';
    /** Clientes elegidos uno a uno (announcement_tenant). */
    case Tenants = 'tenants';
}
