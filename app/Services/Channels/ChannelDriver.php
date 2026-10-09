<?php

declare(strict_types=1);

namespace App\Services\Channels;

use Illuminate\Http\Request;

interface ChannelDriver
{
    public function send(OutboundMessage $message): SendResult;

    /**
     * $sender: cuenta propia del tenant a la que corresponde el webhook (su
     * firma se valida con SUS credenciales); null = cuenta por defecto.
     */
    public function verifyWebhookSignature(Request $request, ?SenderIdentity $sender = null): bool;

    /**
     * @return list<MessageStatusUpdate>
     */
    public function parseWebhook(Request $request): array;
}
