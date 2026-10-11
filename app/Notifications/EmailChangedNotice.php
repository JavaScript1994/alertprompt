<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al correo ANTERIOR de que el cambio se confirmó. */
class EmailChangedNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $userName,
        public readonly string $newEmail,
    ) {}

    /** @return list<string> */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu correo de AlertPrompt cambió')
            ->greeting("Hola, {$this->userName}")
            ->line("Desde ahora inicias sesión con {$this->newEmail}. Este correo ya no sirve para entrar.")
            ->line('Si no reconoces este cambio, comunícate de inmediato con el administrador de tu cuenta.');
    }
}
