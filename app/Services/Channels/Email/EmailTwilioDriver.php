<?php

declare(strict_types=1);

namespace App\Services\Channels\Email;

use App\Services\Channels\ChannelDriver;
use App\Services\Channels\MessageStatusUpdate;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SendResult;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Twilio Email API (comms.twilio.com) — endpoint REST nuevo, sin cobertura
 * todavía en twilio/sdk (8.x), por eso se llama directo con el cliente HTTP
 * de Laravel en vez del SDK generado que usan los demás drivers de Twilio.
 */
class EmailTwilioDriver implements ChannelDriver
{
    public function send(OutboundMessage $message): SendResult
    {
        try {
            $response = Http::withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
                ->asJson()
                ->post('https://comms.twilio.com/v1/Emails', [
                    'from' => [
                        'address' => config('mail.from.address'),
                        'name' => config('mail.from.name'),
                    ],
                    'to' => [
                        ['address' => $message->to],
                    ],
                    'content' => [
                        'subject' => $message->subject ?? config('mail.from.name'),
                        'text' => $message->body,
                        // La API exige 'html' además de 'text' — no acepta
                        // solo texto plano. Derivamos un HTML mínimo del
                        // cuerpo (el mismo texto que ya renderizó TemplateRenderer).
                        'html' => '<p>'.nl2br(e($message->body)).'</p>',
                    ],
                ]);
        } catch (\Throwable) {
            return SendResult::failure('twilio_email_exception', shouldRetry: true);
        }

        if ($response->status() === 202) {
            $operationId = $response->json('operationId') ?? $response->json('sid') ?? (string) $response->status();

            return SendResult::success((string) $operationId, 'accepted');
        }

        return $this->mapErrorResponse($response);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // La Email API de Twilio es un endpoint nuevo (comms.twilio.com) y no
        // hay todavía un esquema de firma de webhook documentado/soportado
        // por el SDK, a diferencia de X-Twilio-Signature (WhatsApp/SMS) o el
        // Event Webhook de SendGrid. Hasta que se confirme ese esquema,
        // rechazamos por defecto en vez de aceptar webhooks sin verificar.
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return [];
    }

    private function mapErrorResponse(Response $response): SendResult
    {
        $statusCode = $response->status();
        $code = (string) $statusCode;

        return match (true) {
            $statusCode === 429 => SendResult::failure($code, shouldRetry: true),
            $statusCode >= 500 => SendResult::failure($code, shouldRetry: true),
            $statusCode === 400 || $statusCode === 422 => SendResult::failure($code, shouldSuppress: true),
            default => SendResult::failure($code, shouldRetry: false),
        };
    }
}
