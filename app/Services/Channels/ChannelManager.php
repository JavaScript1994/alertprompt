<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Models\ChannelAccount;
use App\Models\Scopes\TenantScope;
use InvalidArgumentException;

/**
 * Único lugar que conoce proveedores: traduce canal (+ cuenta del cliente)
 * a un driver concreto según config/channels.php.
 */
final class ChannelManager
{
    /** @var array<string, ChannelDriver> */
    private array $resolved = [];

    /** Driver por defecto del canal (remitente compartido de la plataforma). */
    public function driver(Channel $channel): ChannelDriver
    {
        return $this->driverNamed($channel, config("channels.{$channel->value}.driver"));
    }

    /**
     * Con qué envía un tenant por un canal: su cuenta propia activa o, si no
     * tiene y está permitido, el remitente compartido. Null = no puede enviar.
     */
    public function forTenant(int $tenantId, Channel $channel): ?TenantChannel
    {
        $account = $this->activeAccount($tenantId, $channel);

        if ($account !== null) {
            return new TenantChannel($this->driverNamed($channel, $account->provider), $account->toSenderIdentity());
        }

        return $this->sharedSenderAllowed($channel) ? new TenantChannel($this->driver($channel), null) : null;
    }

    public function canSend(int $tenantId, Channel $channel): bool
    {
        return $this->activeAccount($tenantId, $channel) !== null || $this->sharedSenderAllowed($channel);
    }

    public function sharedSenderAllowed(Channel $channel): bool
    {
        return ! $this->supportsTenantAccounts($channel) || (bool) config('channels.shared_sender', true);
    }

    public function supportsTenantAccounts(Channel $channel): bool
    {
        return array_key_exists($channel->value, config('channels.tenant_accounts', []));
    }

    /** @return list<string> */
    public function providersForTenantAccounts(Channel $channel): array
    {
        return config("channels.tenant_accounts.{$channel->value}", []);
    }

    /** Driver de una cuenta propia, para verificar los webhooks que le llegan. */
    public function driverForAccount(ChannelAccount $account): ChannelDriver
    {
        return $this->driverNamed($account->channel, $account->provider);
    }

    private function activeAccount(int $tenantId, Channel $channel): ?ChannelAccount
    {
        // Sin TenantScope: corre en jobs de cola, sin tenant activo; se filtra
        // explícitamente por el tenant de la campaña.
        return ChannelAccount::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('status', ChannelAccountStatus::Active)
            ->first();
    }

    private function driverNamed(Channel $channel, ?string $driverName): ChannelDriver
    {
        return $this->resolved["{$channel->value}:{$driverName}"] ??= $this->resolve($channel, $driverName);
    }

    private function resolve(Channel $channel, ?string $driverName): ChannelDriver
    {
        $config = config("channels.{$channel->value}");

        if ($config === null) {
            throw new InvalidArgumentException("Canal no configurado: [{$channel->value}].");
        }

        $driverClass = $config['drivers'][$driverName] ?? null;

        if ($driverClass === null) {
            throw new InvalidArgumentException(
                "Driver [{$driverName}] no soportado para el canal [{$channel->value}]."
            );
        }

        return app($driverClass);
    }
}
