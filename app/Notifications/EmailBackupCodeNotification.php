<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailOtpPurpose;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Código de 6 dígitos al correo de respaldo. NO va a la cola a propósito: el
 * payload de un job queda en Redis y, si falla, en `failed_jobs`; el código
 * en claro no debe persistir en ningún lado.
 */
class EmailBackupCodeNotification extends Notification
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $code,
        private readonly EmailOtpPurpose $purpose,
        private readonly string $userName,
    ) {}

    /** @return list<string> */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        $minutes = intdiv((int) config('mfa.email_otp.ttl', 600), 60);

        $message = (new MailMessage)->greeting("Hola, {$this->userName}");

        if ($this->purpose === EmailOtpPurpose::Setup) {
            return $message
                ->subject('Verifica tu correo de respaldo de AlertPrompt')
                ->line('Ingresa este código para confirmar este correo como respaldo de tu verificación en dos pasos:')
                ->line("**{$this->code}**")
                ->line("Vence en {$minutes} minutos. Si no lo pediste, ignora este correo.");
        }

        return $message
            ->subject('Código para entrar sin tu app de autenticación')
            ->line('Alguien inició sesión con tu contraseña y pidió entrar sin la app de autenticación. Tu código es:')
            ->line("**{$this->code}**")
            ->line("Vence en {$minutes} minutos y sirve una sola vez.")
            ->line('Si no fuiste tú, cambia tu contraseña de inmediato y avisa al administrador de tu cuenta.');
    }
}
