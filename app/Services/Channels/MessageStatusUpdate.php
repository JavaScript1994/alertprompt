<?php

declare(strict_types=1);

namespace App\Services\Channels;

final class MessageStatusUpdate
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $providerMessageId,
        public readonly string $event,
        public readonly array $payload = [],
    ) {}
}
