<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro inmutable de acciones sensibles. Sin TenantScope: `tenant_id` es
 * el tenant afectado y el autor suele ser del tenant de la plataforma. Solo
 * se lee desde rutas /api/admin/*.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'user_id', 'action', 'subject_type', 'subject_id', 'metadata', 'ip'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
