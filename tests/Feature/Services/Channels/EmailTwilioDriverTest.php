<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Services\Channels\Email\EmailTwilioDriver;
use App\Services\Channels\OutboundMessage;
use Illuminate\Support\Facades\Http;

function makeTwilioEmailOutboundMessage(): OutboundMessage
{
    return new OutboundMessage(
        channel: Channel::Email,
        to: 'cliente@example.com',
        body: 'Hola Carlos, tu pedido está listo.',
        subject: 'Tu pedido está listo',
    );
}

it('sends an email via the Twilio Email API and returns a successful SendResult', function () {
    Http::fake([
        'comms.twilio.com/*' => Http::response(['operationId' => 'OP123'], 202),
    ]);

    $result = (new EmailTwilioDriver)->send(makeTwilioEmailOutboundMessage());

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('OP123')
        ->and($result->status)->toBe('accepted');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://comms.twilio.com/v1/Emails'
            && $request['to'][0]['address'] === 'cliente@example.com'
            && $request['content']['subject'] === 'Tu pedido está listo'
            && $request['content']['text'] === 'Hola Carlos, tu pedido está listo.'
            && $request['content']['html'] === '<p>Hola Carlos, tu pedido está listo.</p>';
    });
});

it('suppresses on a 400 response', function () {
    Http::fake([
        'comms.twilio.com/*' => Http::response(['message' => 'invalid address'], 400),
    ]);

    $result = (new EmailTwilioDriver)->send(makeTwilioEmailOutboundMessage());

    expect($result->success)->toBeFalse()
        ->and($result->shouldSuppress)->toBeTrue()
        ->and($result->shouldRetry)->toBeFalse();
});

it('retries on 429 and 5xx', function () {
    Http::fake([
        'comms.twilio.com/*' => Http::response([], 429),
    ]);

    $result = (new EmailTwilioDriver)->send(makeTwilioEmailOutboundMessage());

    expect($result->shouldRetry)->toBeTrue();

    Http::fake([
        'comms.twilio.com/*' => Http::response([], 503),
    ]);

    $result = (new EmailTwilioDriver)->send(makeTwilioEmailOutboundMessage());

    expect($result->shouldRetry)->toBeTrue();
});

it('rejects webhooks by default since the signature scheme is not yet supported', function () {
    $driver = new EmailTwilioDriver;

    expect($driver->verifyWebhookSignature(request()))->toBeFalse()
        ->and($driver->parseWebhook(request()))->toBe([]);
});
