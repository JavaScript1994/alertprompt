<?php

declare(strict_types=1);

namespace App\Services\ChannelAccounts;

use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Models\ChannelAccount;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Números propios de los clientes. El cliente SOLICITA (queda pendiente) y
 * la plataforma lo configura con el proveedor y lo activa: las credenciales
 * del proveedor nunca pasan por las manos del cliente.
 */
class ChannelAccountManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function find(Tenant $tenant, Channel $channel): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('channel', $channel)
            ->first();
    }

    /** Pedido del cliente: número y nombre visible. Queda pendiente de activación. */
    public function request(Tenant $tenant, Channel $channel, string $sender, ?string $displayName): ChannelAccount
    {
        return DB::transaction(function () use ($tenant, $channel, $sender, $displayName) {
            $account = $this->find($tenant, $channel) ?? new ChannelAccount([
                'tenant_id' => $tenant->id,
                'channel' => $channel,
                'provider' => config("channels.{$channel->value}.driver"),
            ]);

            $numberChanged = $account->exists && $account->sender !== $sender;

            $account->fill(['sender' => $sender, 'display_name' => $displayName]);

            // Un número nuevo hay que darlo de alta en el proveedor: vuelve a pendiente.
            if (! $account->exists || $numberChanged) {
                $account->fill(['status' => ChannelAccountStatus::Pending, 'activated_at' => null]);
            }

            $account->save();

            $this->audit->record('channel_account.requested', $tenant->id, $account, [
                'channel' => $channel->value,
                'sender' => $sender,
            ]);

            return $account;
        });
    }

    /**
     * Configuración completa por la plataforma.
     *
     * @param  array{provider: string, sender: string, display_name?: ?string, status: string, quality_rating?: ?string, messaging_tier?: ?string, notes?: ?string, credentials?: ?array<string, string>}  $data
     */
    public function configure(Tenant $tenant, Channel $channel, array $data): ChannelAccount
    {
        return DB::transaction(function () use ($tenant, $channel, $data) {
            $account = $this->find($tenant, $channel) ?? new ChannelAccount(['tenant_id' => $tenant->id, 'channel' => $channel]);
            $wasActive = $account->status === ChannelAccountStatus::Active;

            $account->fill([
                'provider' => $data['provider'],
                'sender' => $data['sender'],
                'display_name' => $data['display_name'] ?? null,
                'status' => ChannelAccountStatus::from($data['status']),
                'quality_rating' => $data['quality_rating'] ?? null,
                'messaging_tier' => $data['messaging_tier'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Credenciales: solo se reemplazan si vienen; vacías = conservar.
            if (! empty($data['credentials'])) {
                $account->credentials = array_filter($data['credentials'], fn ($value) => $value !== null && $value !== '');
            }

            if ($account->status === ChannelAccountStatus::Active && ! $wasActive) {
                $account->activated_at = now();
            }

            $changed = array_keys($account->getDirty());
            $account->save();

            $this->audit->record('channel_account.configured', $tenant->id, $account, [
                'channel' => $channel->value,
                'status' => $account->status->value,
                // Nunca el valor de las credenciales, solo si cambiaron.
                'fields' => array_values(array_map(fn ($field) => $field === 'credentials' ? 'credentials (actualizadas)' : $field, $changed)),
            ]);

            return $account;
        });
    }
}
