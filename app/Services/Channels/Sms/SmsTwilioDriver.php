<?php

declare(strict_types=1);

namespace App\Services\Channels\Sms;

use App\Services\Channels\ChannelDriver;
use App\Services\Channels\MessageStatusUpdate;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SenderIdentity;
use App\Services\Channels\SendResult;
use App\Services\Channels\Twilio\TwilioClientFactory;
use Illuminate\Http\Request;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class SmsTwilioDriver implements ChannelDriver
{
    private readonly TwilioClientFactory $clients;

    public function __construct(Client $client, ?TwilioClientFactory $clients = null)
    {
        $this->clients = $clients ?? new TwilioClientFactory($client);
    }

    public function send(OutboundMessage $message): SendResult
    {
        try {
            $params = [
                // Cuenta propia del cliente o, si no tiene, el remitente compartido.
                'from' => $message->sender !== null ? $message->sender->from : config('services.twilio.sms_from'),
                'body' => $message->body,
            ];

            if ($message->statusCallbackUrl !== null) {
                $params['statusCallback'] = $message->statusCallbackUrl;
            }

            $twilioMessage = $this->clients->for($message->sender)->messages->create($message->to, $params);

            return SendResult::success($twilioMessage->sid, $twilioMessage->status);
        } catch (RestException $exception) {
            return $this->mapException($exception);
        }
    }

    public function verifyWebhookSignature(Request $request, ?SenderIdentity $sender = null): bool
    {
        $signature = $request->header('X-Twilio-Signature');

        if ($signature === null) {
            return false;
        }

        $validator = new RequestValidator((string) $this->clients->authTokenFor($sender));

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
