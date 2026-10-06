<?php

declare(strict_types=1);

namespace App\Services\Channels;

use Illuminate\Http\Request;

interface ChannelDriver
{
    public function send(OutboundMessage $message): SendResult;

    public function verifyWebhookSignature(Request $request): bool;

    /**
     * @return list<MessageStatusUpdate>
     */
    public function parseWebhook(Request $request): array;
}
