<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Services\Channels\Email\EmailSmtpDriver;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SendResult;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

function makeSmtpOutboundMessage(): OutboundMessage
{
    return new OutboundMessage(
        channel: Channel::Email,
        to: 'cliente@example.com',
        body: 'Hola Carlos, tu pedido está listo.',
        subject: 'Tu pedido está listo',
    );
}

it('sends an email through the smtp mailer and returns a successful SendResult', function () {
    // Mail::fake()->raw() es un no-op — no registra nada para assertSent().
    // Lo que importa acá es el contrato: que send() no lance y devuelva éxito.
    Mail::fake();

    $result = (new EmailSmtpDriver)->send(makeSmtpOutboundMessage());

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe('accepted')
        ->and($result->providerMessageId)->not->toBeNull();
});

/**
 * mapTransportException() es privado — se ejerce vía reflexión porque, a
 * diferencia de los drivers de API (SendGrid/Twilio) que reciben un cliente
 * mockeable por constructor, este driver usa el facade Mail internamente y
 * Mail::fake() nunca llega a invocar el transporte real, así que no hay
 * forma de simular un TransportExceptionInterface a través del contrato público.
 */
function mapSmtpTransportException(string $message): SendResult
{
    $driver = new EmailSmtpDriver;
    $method = new ReflectionMethod($driver, 'mapTransportException');
    $method->setAccessible(true);

    return $method->invoke($driver, new TransportException($message));
}

it('suppresses and does not retry on a recipient rejection', function () {
    $result = mapSmtpTransportException('Expected response code 250 but got code "550", with message "550 Recipient address rejected"');

    expect($result->success)->toBeFalse()
        ->and($result->shouldSuppress)->toBeTrue()
        ->and($result->shouldRetry)->toBeFalse();
});

it('halts the campaign on an authentication failure', function () {
    $result = mapSmtpTransportException('Expected response code 235 but got code "535", with message "535 Authentication failed"');

    expect($result->success)->toBeFalse()
        ->and($result->shouldHaltCampaign)->toBeTrue();
});

it('retries on a generic transport error', function () {
    $result = mapSmtpTransportException('Connection could not be established with host "alertprompt.qerava.com:465"');

    expect($result->success)->toBeFalse()
        ->and($result->shouldRetry)->toBeTrue();
});

it('rejects webhooks: a plain smtp server never calls back', function () {
    $driver = new EmailSmtpDriver;

    expect($driver->verifyWebhookSignature(request()))->toBeFalse()
        ->and($driver->parseWebhook(request()))->toBe([]);
});
