<?php

declare(strict_types=1);

namespace App\Services\Channels\WhatsApp;

use App\Exceptions\NotImplementedException;
use App\Services\Channels\ChannelDriver;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SenderIdentity;
use App\Services\Channels\SendResult;
use Illuminate\Http\Request;

/**
 * Producción — Meta Cloud API directa (sin BSP intermediario).
 *
 * Fuera de alcance del MVP: requiere Business Manager verificado y
 * plantillas aprobadas por Meta, que tardan días/semanas. Ver CLAUDE.md §4-bis.
 */
class WhatsAppCloudDriver implements ChannelDriver
{
    public function send(OutboundMessage $message): SendResult
    {
        throw new NotImplementedException(
            'WhatsAppCloudDriver no está implementado todavía. Usa WHATSAPP_DRIVER=twilio para el MVP.'
        );
    }

    public function verifyWebhookSignature(Request $request, ?SenderIdentity $sender = null): bool
    {
        throw new NotImplementedException('WhatsAppCloudDriver no está implementado todavía.');
    }

    public function parseWebhook(Request $request): array
    {
        throw new NotImplementedException('WhatsAppCloudDriver no está implementado todavía.');
    }
}
