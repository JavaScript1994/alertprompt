<?php

declare(strict_types=1);

namespace App\Services\Channels;

use DateTimeInterface;

final class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId,
        public readonly string $status,
        public readonly ?string $errorCode = null,
        public readonly bool $shouldRetry = false,
        public readonly bool $shouldSuppress = false,
        // Códigos como 131031 (cuenta bloqueada) no son un fallo de ESTE
        // mensaje sino de la cuenta completa — la campaña debe pausarse ya,
        // no seguir gastando cuota en el resto del lote.
        public readonly bool $shouldHaltCampaign = false,
        // 131048 (spam rate): no reintentar ya, sino después de medianoche UTC.
        public readonly ?DateTimeInterface $retryAfter = null,
    ) {}

    public static function success(string $providerMessageId, string $status = 'queued'): self
    {
        return new self(success: true, providerMessageId: $providerMessageId, status: $status);
    }

    public static function failure(
        string $errorCode,
        string $status = 'failed',
        bool $shouldRetry = false,
        bool $shouldSuppress = false,
        bool $shouldHaltCampaign = false,
        ?DateTimeInterface $retryAfter = null,
    ): self {
        return new self(
            success: false,
            providerMessageId: null,
            status: $status,
            errorCode: $errorCode,
            shouldRetry: $shouldRetry,
            shouldSuppress: $shouldSuppress,
            shouldHaltCampaign: $shouldHaltCampaign,
            retryAfter: $retryAfter,
        );
    }
}
