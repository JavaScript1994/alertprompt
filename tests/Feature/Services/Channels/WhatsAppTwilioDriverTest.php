<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\WhatsApp\WhatsAppTwilioDriver;
use Twilio\Http\Client as TwilioHttpClient;
use Twilio\Http\Response as TwilioHttpResponse;
use Twilio\Rest\Client;

afterEach(function () {
    Mockery::close();
});

/**
 * Crea un Client real (sin hits de red) con su HttpClient interno mockeado —
 * el seam de testing que el propio SDK expone, en vez de mockear el magic
 * __get de propiedades como $client->messages (no interceptable con Mockery).
 */
function twilioClientWithResponse(int $statusCode, array $body): Client
{
    $httpClient = Mockery::mock(TwilioHttpClient::class);
    $httpClient->shouldReceive('request')
        ->once()
        ->andReturn(new TwilioHttpResponse($statusCode, json_encode($body)));

    return new Client('ACxxxx', 'token', 'ACxxxx', null, $httpClient);
}

function makeOutboundMessage(): OutboundMessage
{
    return new OutboundMessage(
        channel: Channel::WhatsApp,
        to: '+51987654321',
        body: 'Hola Carlos, tu pedido está listo.',
    );
}

it('sends a whatsapp message and returns a successful SendResult', function () {
    $client = twilioClientWithResponse(201, ['sid' => 'SM123', 'status' => 'queued']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('SM123')
        ->and($result->status)->toBe('queued');
});

it('marks an invalid number as failed and suppressible, without retry', function () {
    $client = twilioClientWithResponse(400, ['code' => 21211, 'message' => 'Invalid number']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('21211')
        ->and($result->shouldSuppress)->toBeTrue()
        ->and($result->shouldRetry)->toBeFalse();
});

it('halts the campaign on account-level error 131031', function () {
    $client = twilioClientWithResponse(400, ['code' => 131031, 'message' => 'Account restricted']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->shouldHaltCampaign)->toBeTrue()
        ->and($result->shouldRetry)->toBeFalse();
});

it('reschedules after UTC midnight on spam rate error 131048', function () {
    $client = twilioClientWithResponse(400, ['code' => 131048, 'message' => 'Spam rate limit']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->shouldRetry)->toBeTrue()
        ->and($result->retryAfter)->not->toBeNull()
        ->and($result->retryAfter->format('H:i:s'))->toBe('00:00:00');
});

it('skips 48h without retrying on frequency-cap error 131049', function () {
    $client = twilioClientWithResponse(400, ['code' => 131049, 'message' => 'Frequency cap']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->shouldRetry)->toBeFalse()
        ->and($result->retryAfter)->not->toBeNull();
});

it('retries once shortly after a transient service error 131016', function () {
    $client = twilioClientWithResponse(400, ['code' => 131016, 'message' => 'Service error']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->shouldRetry)->toBeTrue();
});

it('retries on HTTP 429 rate limiting', function () {
    $client = twilioClientWithResponse(429, ['code' => 20429, 'message' => 'Too many requests']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->shouldRetry)->toBeTrue();
});

it('retries up to 3 times on a generic 5xx error', function () {
    $client = twilioClientWithResponse(500, ['code' => 20500, 'message' => 'Server error']);

    $result = (new WhatsAppTwilioDriver($client))->send(makeOutboundMessage());

    expect($result->shouldRetry)->toBeTrue();
});
