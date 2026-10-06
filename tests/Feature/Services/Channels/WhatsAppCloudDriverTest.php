<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Exceptions\NotImplementedException;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\WhatsApp\WhatsAppCloudDriver;
use Illuminate\Http\Request;

it('throws NotImplementedException on send', function () {
    (new WhatsAppCloudDriver)->send(new OutboundMessage(Channel::WhatsApp, '+51987654321', 'Hola'));
})->throws(NotImplementedException::class);

it('throws NotImplementedException on webhook methods', function () {
    $driver = new WhatsAppCloudDriver;
    $request = Request::create('/webhooks/whatsapp', 'POST');

    expect(fn () => $driver->verifyWebhookSignature($request))->toThrow(NotImplementedException::class);
    expect(fn () => $driver->parseWebhook($request))->toThrow(NotImplementedException::class);
});
