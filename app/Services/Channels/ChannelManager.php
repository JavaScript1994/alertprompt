<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Enums\Channel;
use InvalidArgumentException;

final class ChannelManager
{
    /** @var array<string, ChannelDriver> */
    private array $resolved = [];

    public function driver(Channel $channel): ChannelDriver
    {
        return $this->resolved[$channel->value] ??= $this->resolve($channel);
    }

    private function resolve(Channel $channel): ChannelDriver
    {
        $config = config("channels.{$channel->value}");

        if ($config === null) {
            throw new InvalidArgumentException("Canal no configurado: [{$channel->value}].");
        }

        $driverName = $config['driver'];
        $driverClass = $config['drivers'][$driverName] ?? null;

        if ($driverClass === null) {
            throw new InvalidArgumentException(
                "Driver [{$driverName}] no soportado para el canal [{$channel->value}]."
            );
        }

        return app($driverClass);
    }
}
