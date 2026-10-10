<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora inmutable de autenticación. Sin TenantScope (como AuditLog): se
 * escribe antes de que haya sesión y tenant. `metadata` solo lleva contexto
 * (resultado, motivo); nunca secretos, códigos ni tokens.
 */
class AuthEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'tenant_id', 'event', 'ip', 'user_agent', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'event' => AuthEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
