<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\Sms\SmsTwilioDriver;
use Twilio\Http\Client as TwilioHttpClient;
use Twilio\Http\Response as TwilioHttpResponse;
use Twilio\Rest\Client;

afterEach(function () {
    Mockery::close();
});

function smsTwilioClientWithResponse(int $statusCode, array $body): Client
{
    $httpClient = Mockery::mock(TwilioHttpClient::class);
    $httpClient->shouldReceive('request')
        ->once()
        ->andReturn(new TwilioHttpResponse($statusCode, json_encode($body)));

    return new Client('ACxxxx', 'token', 'ACxxxx', null, $httpClient);
}

function makeSmsOutboundMessage(): OutboundMessage
{
    return new OutboundMessage(
        channel: Channel::Sms,
        to: '+51987654321',
        body: 'Hola Carlos, tu pedido está listo.',
    );
}

it('sends an sms and returns a successful SendResult', function () {
    $client = smsTwilioClientWithResponse(201, ['sid' => 'SM123', 'status' => 'queued']);

    $result = (new SmsTwilioDriver($client))->send(makeSmsOutboundMessage());

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('SM123');
});

it('suppresses and does not retry on invalid number 21211', function () {
    $client = smsTwilioClientWithResponse(400, ['code' => 21211, 'message' => 'Invalid number']);

    $result = (new SmsTwilioDriver($client))->send(makeSmsOutboundMessage());

    expect($result->shouldSuppress)->toBeTrue()->and($result->shouldRetry)->toBeFalse();
});

it('suppresses on an unsubscribed recipient 21610', function () {
    $client = smsTwilioClientWithResponse(400, ['code' => 21610, 'message' => 'Unsubscribed']);

    $result = (new SmsTwilioDriver($client))->send(makeSmsOutboundMessage());

    expect($result->shouldSuppress)->toBeTrue();
});

it('retries on 429 and 5xx', function () {
    $rateLimited = (new SmsTwilioDriver(
        smsTwilioClientWithResponse(429, ['code' => 20429, 'message' => 'Too many'])
    ))->send(makeSmsOutboundMessage());

    $serverError = (new SmsTwilioDriver(
        smsTwilioClientWithResponse(500, ['code' => 20500, 'message' => 'Server error'])
    ))->send(makeSmsOutboundMessage());

    expect($rateLimited->shouldRetry)->toBeTrue()->and($serverError->shouldRetry)->toBeTrue();
});
