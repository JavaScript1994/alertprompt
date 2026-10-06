<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Services\Channels\Email\EmailSendGridDriver;
use App\Services\Channels\OutboundMessage;
use SendGrid\Mail\Mail;
use SendGrid\Response;

afterEach(function () {
    Mockery::close();
});

function makeEmailOutboundMessage(): OutboundMessage
{
    return new OutboundMessage(
        channel: Channel::Email,
        to: 'cliente@example.com',
        body: 'Hola Carlos, tu pedido está listo.',
        subject: 'Tu pedido está listo',
    );
}

it('sends an email and returns a successful SendResult', function () {
    $client = Mockery::mock(SendGrid::class);
    $client->shouldReceive('send')
        ->once()
        ->with(Mockery::type(Mail::class))
        ->andReturn(new Response(202, '', ['X-Message-Id: abc123.filter001']));

    $result = (new EmailSendGridDriver($client))->send(makeEmailOutboundMessage());

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('abc123.filter001');
});

it('suppresses on a 400 response', function () {
    $client = Mockery::mock(SendGrid::class);
    $client->shouldReceive('send')->once()->andReturn(new Response(400, '{"errors":[]}'));

    $result = (new EmailSendGridDriver($client))->send(makeEmailOutboundMessage());

    expect($result->shouldSuppress)->toBeTrue()->and($result->shouldRetry)->toBeFalse();
});

it('retries on 429 and 5xx', function () {
    $rateLimited = Mockery::mock(SendGrid::class);
    $rateLimited->shouldReceive('send')->once()->andReturn(new Response(429, ''));

    $serverError = Mockery::mock(SendGrid::class);
    $serverError->shouldReceive('send')->once()->andReturn(new Response(503, ''));

    $resultA = (new EmailSendGridDriver($rateLimited))->send(makeEmailOutboundMessage());
    $resultB = (new EmailSendGridDriver($serverError))->send(makeEmailOutboundMessage());

    expect($resultA->shouldRetry)->toBeTrue()->and($resultB->shouldRetry)->toBeTrue();
});

it('retries when the sdk throws an exception', function () {
    $client = Mockery::mock(SendGrid::class);
    $client->shouldReceive('send')->once()->andThrow(new Exception('network error'));

    $result = (new EmailSendGridDriver($client))->send(makeEmailOutboundMessage());

    expect($result->shouldRetry)->toBeTrue();
});
