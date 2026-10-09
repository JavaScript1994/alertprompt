<?php

declare(strict_types=1);

namespace App\Services\Channels;

/** Driver + remitente con los que un tenant envía por un canal. */
final class TenantChannel
{
    public function __construct(
        public readonly ChannelDriver $driver,
        public readonly ?SenderIdentity $sender,
    ) {}
}
