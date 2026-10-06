<?php

declare(strict_types=1);

namespace App\Services\Channels\Email;

use App\Services\Channels\ChannelDriver;
use App\Services\Channels\OutboundMessage;
use App\Services\Channels\SendResult;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Servidor SMTP propio (ej. cPanel/webmail), vía el mailer "smtp" de Laravel.
 *
 * A diferencia de los drivers de API (SendGrid, Twilio), un servidor SMTP
 * genérico no tiene mecanismo de webhook de estado: no hay forma de saber si
 * el mensaje fue entregado, rebotó o fue leído — solo si el servidor lo
 * ACEPTÓ para enviar. Los recipients quedan en "sent" y ahí se quedan; el
 * pacer/dashboard en vivo no recibe señales de este canal.
 */
class EmailSmtpDriver implements ChannelDriver
{
    public function send(OutboundMessage $message): SendResult
    {
        try {
            Mail::mailer('smtp')->raw($message->body, function (Message $mail) use ($message) {
                $mail->to($message->to)
                    ->subject($message->subject ?? config('mail.from.name'));
            });
        } catch (TransportExceptionInterface $exception) {
            return $this->mapTransportException($exception);
        } catch (\Throwable) {
            return SendResult::failure('smtp_exception', shouldRetry: true);
        }

        // El SMTP crudo no devuelve un id del proveedor como las APIs — se
        // genera uno propio solo para poder rastrear el envío internamente.
        return SendResult::success((string) Str::uuid(), 'accepted');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // Un servidor SMTP genérico no llama de vuelta a ningún webhook —
        // no hay nada que verificar porque nunca va a llegar nada acá.
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return [];
    }

    private function mapTransportException(TransportExceptionInterface $exception): SendResult
    {
        $message = $exception->getMessage();

        // Rechazo explícito del destinatario (buzón inexistente, dominio
        // inválido) — no tiene sentido reintentar, y conviene suprimir.
        if (str_contains($message, '550') || str_contains($message, 'Recipient address rejected')) {
            return SendResult::failure('smtp_recipient_rejected', shouldSuppress: true);
        }

        // Fallo de autenticación con el servidor: afecta a toda la cuenta,
        // no a este mensaje puntual — no tiene sentido seguir intentando el
        // resto del lote con las mismas credenciales rotas.
        if (str_contains($message, '535') || stripos($message, 'authentication') !== false) {
            return SendResult::failure('smtp_auth_failed', shouldHaltCampaign: true);
        }

        return SendResult::failure('smtp_transport_error', shouldRetry: true);
    }
}
