<?php

declare(strict_types=1);

namespace App\Services\Channels;

/**
 * Remitente con el que se envía un mensaje: el número propio del cliente y,
 * si su cuenta en el proveedor es separada (p. ej. subcuenta de Twilio), sus
 * credenciales. Null en OutboundMessage = remitente compartido por defecto.
 */
final class SenderIdentity
{
    /** @param  array<string, string>  $credentials */
    public function __construct(
        public readonly string $from,
        public readonly array $credentials = [],
        public readonly ?int $accountId = null,
    ) {}

    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
