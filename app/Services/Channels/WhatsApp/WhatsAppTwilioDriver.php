<?php

declare(strict_types=1);

namespace App\Services\Channels\WhatsApp;

use App\Services\Channels\ChannelDriver;
use App\Services\Channels\MessageStatusUpdate;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class WhatsAppTwilioDriver implements ChannelDriver
{
    public function __construct(private readonly Client $client) {}

    public function send(OutboundMessage $message): SendResult
    {
        try {
            $twilioMessage = $this->client->messages->create(
                'whatsapp:'.$message->to,
                [
                    'from' => config('services.twilio.whatsapp_from'),
                    'body' => $message->body,
                ],
            );

            return SendResult::success($twilioMessage->sid, $twilioMessage->status);
        } catch (RestException $exception) {
            return $this->mapException($exception);
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Twilio-Signature');

        if ($signature === null) {
            return false;
        }

        $validator = new RequestValidator(config('services.twilio.token'));

        return $validator->validate($signature, $request->fullUrl(), $request->all());
    }

    public function parseWebhook(Request $request): array
    {
        $providerMessageId = $request->string('MessageSid')->toString();

        if ($providerMessageId === '') {
            return [];
        }

        return [
            new MessageStatusUpdate(
                providerMessageId: $providerMessageId,
                event: $request->string('MessageStatus')->toString(),
                payload: $request->all(),
            ),
        ];
    }

    /**
     * Mapeo de códigos de error según CLAUDE.md §6.3.
     */
    private function mapException(RestException $exception): SendResult
    {
        $code = (string) $exception->getCode();
        $statusCode = $exception->getStatusCode();

        return match (true) {
            // Twilio-level: número inválido.
            $code === '21211' => SendResult::failure($code, shouldSuppress: true),

            // Meta/WhatsApp: cuenta bloqueada — parar toda la campaña.
            $code === '131031' => SendResult::failure($code, shouldHaltCampaign: true),

            // Meta/WhatsApp: spam rate — reprogramar tras medianoche UTC.
            $code === '131048' => SendResult::failure(
                $code,
                shouldRetry: true,
                retryAfter: Carbon::now('UTC')->addDay()->startOfDay(),
            ),

            // Meta/WhatsApp: límite de frecuencia — omitir, no reintentar ahora.
            $code === '131049' => SendResult::failure(
                $code,
                shouldRetry: false,
                retryAfter: Carbon::now('UTC')->addHours(48),
            ),

            // Meta/WhatsApp: error de servicio transitorio — un reintento corto.
            $code === '131016' => SendResult::failure(
                $code,
                shouldRetry: true,
                retryAfter: Carbon::now('UTC')->addSeconds(45),
            ),

            // Rate limit de la API de Twilio en sí.
            $statusCode === 429 => SendResult::failure($code, shouldRetry: true),

            // 5xx genérico: reintentar con el backoff por defecto del job.
            $statusCode >= 500 => SendResult::failure($code, shouldRetry: true),

            default => SendResult::failure($code, shouldRetry: false),
        };
    }
}
