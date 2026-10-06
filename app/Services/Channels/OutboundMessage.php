<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Enums\Channel;

final class OutboundMessage
{
    /**
     * @param  array<string, mixed>  $variables  Datos para renderizar la plantilla ({{var}})
     */
    public function __construct(
        public readonly Channel $channel,
        public readonly string $to,
        public readonly string $body,
        public readonly array $variables = [],
        public readonly ?string $providerTemplateId = null,
        public readonly ?string $subject = null,
    ) {}
}
