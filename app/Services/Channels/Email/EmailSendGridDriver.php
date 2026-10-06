<?php

declare(strict_types=1);

namespace App\Services\Channels\Email;

use App\Services\Channels\ChannelDriver;
use App\Services\Channels\MessageStatusUpdate;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SendResult;
use Illuminate\Http\Request;
use SendGrid;
use SendGrid\EventWebhook\EventWebhook;
use SendGrid\Mail\From;
use SendGrid\Mail\Mail;
use SendGrid\Mail\PlainTextContent;
use SendGrid\Mail\To;
use SendGrid\Response;

class EmailSendGridDriver implements ChannelDriver
{
    public function __construct(private readonly SendGrid $client) {}

    public function send(OutboundMessage $message): SendResult
    {
        $email = new Mail(
            from: new From(config('mail.from.address'), config('mail.from.name')),
            to: new To($message->to),
            subject: $message->subject ?? config('mail.from.name'),
            plainTextContent: new PlainTextContent($message->body),
        );

        try {
            /** @var Response $response */
            $response = $this->client->send($email);
        } catch (\Throwable $exception) {
            return SendResult::failure('sendgrid_exception', shouldRetry: true);
        }

        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            // headers(true) devuelve un array asociativo "Header: valor" con
            // la key tal cual la mandó el servidor — normalizamos a minúscula
            // porque no podemos confiar en el casing exacto.
            $headers = array_change_key_case($response->headers(true), CASE_LOWER);
            $messageId = $headers['x-message-id'] ?? (string) $response->statusCode();

            return SendResult::success($messageId, 'accepted');
        }

        return $this->mapErrorResponse($response);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');
        $publicKey = config('services.sendgrid.webhook_public_key');

        if ($signature === null || $timestamp === null || empty($publicKey)) {
            return false;
        }

        $verifier = new EventWebhook;
        $ecdsaKey = $verifier->convertPublicKeyToECDSA($publicKey);

        return $verifier->verifySignature($ecdsaKey, $request->getContent(), $signature, $timestamp);
    }

    public function parseWebhook(Request $request): array
    {
        $events = $request->json()->all();
        $events = is_array($events) && array_is_list($events) ? $events : [$events];

        $updates = [];

        foreach ($events as $event) {
            if (empty($event['sg_message_id'])) {
                continue;
            }

            $updates[] = new MessageStatusUpdate(
                providerMessageId: explode('.', (string) $event['sg_message_id'])[0],
                event: (string) ($event['event'] ?? 'unknown'),
                payload: $event,
            );
        }

        return $updates;
    }

    private function mapErrorResponse(Response $response): SendResult
    {
        $statusCode = $response->statusCode();
        $code = (string) $statusCode;

        return match (true) {
            $statusCode === 429 => SendResult::failure($code, shouldRetry: true),
            $statusCode >= 500 => SendResult::failure($code, shouldRetry: true),
            $statusCode === 400 || $statusCode === 422 => SendResult::failure($code, shouldSuppress: true),
            default => SendResult::failure($code, shouldRetry: false),
        };
    }
}
