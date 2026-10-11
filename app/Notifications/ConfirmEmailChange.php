<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Enlace al correo NUEVO para confirmar el cambio de correo de inicio de sesión. */
class ConfirmEmailChange extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $userName,
        public readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirma tu nuevo correo de AlertPrompt')
            ->greeting("Hola, {$this->userName}")
            ->line('El administrador de tu cuenta cambió tu correo de inicio de sesión a esta dirección.')
            ->action('Confirmar este correo', $this->url)
            ->line('Hasta que lo confirmes sigues entrando con tu correo anterior. El enlace vence en 24 horas.')
            ->line('Si no esperabas este cambio, ignora este correo y avisa al administrador de tu cuenta.');
    }
}
