<?php

declare(strict_types=1);

namespace App\Services\ChannelAccounts;

use App\Enums\AlertSeverity;
use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Enums\QualityRating;
use App\Models\ChannelAccount;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Alerts;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Números propios de los clientes. El cliente SOLICITA (queda pendiente) y
 * la plataforma lo configura con el proveedor y lo activa: las credenciales
 * del proveedor nunca pasan por las manos del cliente.
 */
class ChannelAccountManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Alerts $alerts,
    ) {}

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
            $previousQuality = $account->quality_rating;

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

            $this->alertOnQualityDrop($tenant, $account, $previousQuality);

            return $account;
        });
    }

    /** Calidad amarilla o roja en Meta: es lo que tumba un número (CLAUDE.md §2.2). */
    private function alertOnQualityDrop(Tenant $tenant, ChannelAccount $account, ?QualityRating $previous): void
    {
        $current = $account->quality_rating;

        if ($current === $previous || ! in_array($current, [QualityRating::Yellow, QualityRating::Red], true)) {
            return;
        }

        $this->alerts->raise(
            tenantId: $tenant->id,
            type: 'channel.quality_drop',
            severity: $current === QualityRating::Red ? AlertSeverity::Critical : AlertSeverity::Warning,
            title: "Calidad {$current->value} en el número {$account->sender}",
            message: $current === QualityRating::Red
                ? 'Meta puede limitar o bloquear el número. Pausa las campañas de marketing y revisa consentimientos y frecuencia.'
                : 'La calidad bajó. Revisa bajas y reportes recientes antes de la próxima campaña.',
            data: ['account_id' => $account->id, 'from' => $previous?->value, 'to' => $current->value],
            subjectKey: "account-{$account->id}",
        );
    }
}
