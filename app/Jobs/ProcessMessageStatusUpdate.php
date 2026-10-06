<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CampaignRecipientStatus;
use App\Enums\Channel;
use App\Enums\SuppressionReason;
use App\Models\CampaignRecipient;
use App\Models\MessageEvent;
use App\Services\Channels\MessageStatusUpdate;
use App\Services\SuppressionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMessageStatusUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Channel $channel,
        public readonly MessageStatusUpdate $update,
    ) {}

    public function handle(SuppressionService $suppressions): void
    {
        $recipient = CampaignRecipient::query()
            ->where('provider_message_id', $this->update->providerMessageId)
            ->with(['campaign', 'contact'])
            ->first();

        if ($recipient === null) {
            Log::warning('Webhook para un provider_message_id desconocido.', [
                'channel' => $this->channel->value,
                'provider_message_id' => $this->update->providerMessageId,
                'event' => $this->update->event,
            ]);

            return;
        }

        // Idempotencia: el mismo evento puede llegar dos veces. Si ya está
        // grabado, no reprocesamos nada más (ni status ni supresión).
        $alreadyRecorded = MessageEvent::query()
            ->where('campaign_recipient_id', $recipient->id)
            ->where('event', $this->update->event)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        MessageEvent::create([
            'campaign_recipient_id' => $recipient->id,
            'event' => $this->update->event,
            'payload' => $this->update->payload,
            'occurred_at' => now(),
        ]);

        $status = $this->statusFor($this->update->event);

        if ($status !== null && ! $recipient->status->isTerminal()) {
            $recipient->update(array_filter([
                'status' => $status,
                'delivered_at' => $status === CampaignRecipientStatus::Delivered ? now() : null,
            ]));
        }

        $suppressionReason = $this->suppressionReasonFor($this->update->event);

        if ($suppressionReason !== null) {
            $identifier = $this->channel === Channel::Email
                ? $recipient->contact->email
                : $recipient->contact->phone;

            if ($identifier !== null) {
                $suppressions->suppress($recipient->campaign->tenant_id, $this->channel, $identifier, $suppressionReason);
            }
        }
    }

    private function statusFor(string $event): ?CampaignRecipientStatus
    {
        return match ($this->channel) {
            Channel::WhatsApp, Channel::Sms => match ($event) {
                'sent' => CampaignRecipientStatus::Sent,
                'delivered' => CampaignRecipientStatus::Delivered,
                'read' => CampaignRecipientStatus::Read,
                'failed', 'undelivered' => CampaignRecipientStatus::Failed,
                default => null,
            },
            Channel::Email => match ($event) {
                'delivered' => CampaignRecipientStatus::Delivered,
                'bounce', 'dropped' => CampaignRecipientStatus::Failed,
                default => null,
            },
        };
    }

    /**
     * §6.4: un bounce duro o un unsubscribe escribe en suppressions automáticamente.
     */
    private function suppressionReasonFor(string $event): ?SuppressionReason
    {
        if ($this->channel !== Channel::Email) {
            return null;
        }

        return match ($event) {
            'bounce' => SuppressionReason::HardBounce,
            'unsubscribe' => SuppressionReason::Unsubscribed,
            'spamreport' => SuppressionReason::SpamComplaint,
            default => null,
        };
    }
}
