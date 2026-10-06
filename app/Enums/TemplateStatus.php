<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplateStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disabled = 'disabled';
}
