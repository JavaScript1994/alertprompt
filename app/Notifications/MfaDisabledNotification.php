<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** La verificación en dos pasos de un usuario se desactivó o se restableció. */
class MfaDisabledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $subjectUserId,
        public readonly string $subjectName,
        public readonly string $subjectEmail,
        public readonly ?string $actorName,
        public readonly ?string $ip,
        public readonly string $occurredAt,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $self = $notifiable->id === $this->subjectUserId;
        $who = $self ? 'de tu usuario' : "de {$this->subjectName} ({$this->subjectEmail})";
        $by = $this->actorName !== null ? " por {$this->actorName}" : '';

        return (new MailMessage)
            ->subject('Se desactivó una verificación en dos pasos en AlertPrompt')
            ->greeting("Hola, {$notifiable->name}")
            ->line("La verificación en dos pasos {$who} se desactivó{$by}. Tendrá que configurarla de nuevo al iniciar sesión.")
            ->line("Fecha: {$this->occurredAt}".($this->ip !== null ? " · IP: {$this->ip}" : ''))
            ->line('Si no lo esperabas, revisa los accesos de la cuenta y cambia las contraseñas.');
    }
}
