<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** @param  array<string, mixed>  $metadata */
    public function record(string $action, ?int $tenantId = null, ?Model $subject = null, array $metadata = []): AuditLog
    {
        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip' => Request::ip(),
        ]);
    }
}
