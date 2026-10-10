<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Escribe en `auth_events`. El metadata es solo contexto (resultado, motivo,
 * método): quien llama nunca debe pasar secretos, códigos ni tokens.
 */
class AuthEventRecorder
{
    /** @param  array<string, scalar|null>  $metadata */
    public function record(AuthEventType $event, ?User $user, array $metadata = []): AuthEvent
    {
        return AuthEvent::query()->create([
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'event' => $event,
            'ip' => Request::ip(),
            'user_agent' => Str::limit((string) Request::userAgent(), 250, ''),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
