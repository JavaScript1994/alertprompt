<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\SkipReason;
use App\Enums\SuppressionReason;
use App\Models\CampaignRecipient;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\OutboundMessage;
use App\Services\SuppressionService;
use App\Services\TemplateRenderer;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $campaignRecipientId) {}

    public function handle(ChannelManager $channels, SuppressionService $suppressions, TemplateRenderer $renderer): void
    {
        $recipient = CampaignRecipient::query()->with(['campaign.template', 'contact'])->find($this->campaignRecipientId);

        // Idempotencia: si ya está en estado terminal (por un retry duplicado
        // o porque el webhook llegó antes que el reintento), no reenviar.
        if ($recipient === null || $recipient->status->isTerminal()) {
            return;
        }

        $campaign = $recipient->campaign;
        $contact = $recipient->contact;
        $channel = $campaign->channel;
        $identifier = $channel === Channel::Email ? $contact->email : $contact->phone;

        if ($identifier === null) {
            $this->skip($recipient, $channel === Channel::Email ? SkipReason::InvalidEmail : SkipReason::InvalidNumber);

            return;
        }

        $template = $campaign->template;

        if ($template->requiresConsent() && ! $contact->hasConsentFor($channel->value)) {
            $this->skip($recipient, SkipReason::NoConsent);

            return;
        }

        // Se consulta acá adentro y no solo al armar la campaña: entre
        // encolar y enviar, el contacto pudo haberse dado de baja.
        if ($suppressions->isSuppressed($campaign->tenant_id, $channel, $identifier)) {
            $this->skip($recipient, SkipReason::Suppressed);

            return;
        }

        // Número propio del cliente o remitente compartido. Si no hay con
        // qué enviar (sin cuenta y sin remitente compartido), falla sin
        // reintento: reintentar no lo arregla.
        $tenantChannel = $channels->forTenant($campaign->tenant_id, $channel);

        if ($tenantChannel === null) {
            $recipient->update([
                'status' => CampaignRecipientStatus::Failed,
                'error_code' => 'no_sender_account',
            ]);

            return;
        }

        $body = $renderer->render($template->body, $contact->attributes ?? []);

        $message = new OutboundMessage(
            channel: $channel,
            to: $identifier,
            body: $body,
            providerTemplateId: $template->provider_template_id,
            sender: $tenantChannel->sender,
            statusCallbackUrl: $tenantChannel->sender?->accountId !== null
                ? route('webhooks.account', ['channel' => $channel->value, 'account' => $tenantChannel->sender->accountId])
                : null,
        );

        $result = $tenantChannel->driver->send($message);

        if ($result->success) {
            $recipient->update([
                'status' => CampaignRecipientStatus::Sent,
                'provider_message_id' => $result->providerMessageId,
                'sent_at' => now(),
            ]);

            return;
        }

        if ($result->shouldSuppress) {
            $suppressions->suppress($campaign->tenant_id, $channel, $identifier, SuppressionReason::InvalidNumber);
        }

        if ($result->shouldHaltCampaign) {
            Log::critical('Campaña detenida por error de cuenta del proveedor.', [
                'campaign_id' => $campaign->id,
                'error_code' => $result->errorCode,
            ]);
            $campaign->transitionTo(CampaignStatus::Paused);
        }

        $recipient->update([
            'status' => CampaignRecipientStatus::Failed,
            'error_code' => $result->errorCode,
        ]);

        if ($result->shouldRetry && $this->attempts() < $this->tries) {
            $this->release($this->delayFor($result->retryAfter));
        }
    }

    private function delayFor(?DateTimeInterface $retryAfter): int
    {
        if ($retryAfter === null) {
            return $this->backoff[$this->attempts() - 1] ?? (int) end($this->backoff);
        }

        return max(0, $retryAfter->getTimestamp() - now()->getTimestamp());
    }

    private function skip(CampaignRecipient $recipient, SkipReason $reason): void
    {
        $recipient->update([
            'status' => CampaignRecipientStatus::Skipped,
            'skip_reason' => $reason,
        ]);
    }
}
