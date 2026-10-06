<?php

declare(strict_types=1);

namespace App\Services\Channels\Sms;

use App\Services\Channels\ChannelDriver;
use App\Services\Channels\MessageStatusUpdate;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SendResult;
use Illuminate\Http\Request;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class SmsTwilioDriver implements ChannelDriver
{
    public function __construct(private readonly Client $client) {}

    public function send(OutboundMessage $message): SendResult
    {
        try {
            $twilioMessage = $this->client->messages->create(
                $message->to,
                [
                    'from' => config('services.twilio.sms_from'),
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

    private function mapException(RestException $exception): SendResult
    {
        $code = (string) $exception->getCode();
        $statusCode = $exception->getStatusCode();

        return match (true) {
            // Número inválido o dado de baja (respondió STOP).
            in_array($code, ['21211', '21610'], true) => SendResult::failure($code, shouldSuppress: true),

            $statusCode === 429 => SendResult::failure($code, shouldRetry: true),
            $statusCode >= 500 => SendResult::failure($code, shouldRetry: true),

            default => SendResult::failure($code, shouldRetry: false),
        };
    }
}
