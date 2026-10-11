<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\AuthEventType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los administradores de la cuenta (y al propio usuario) de que se
 * usó o se cambió un método de respaldo del segundo factor.
 */
class BackupMethodUsedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $subjectUserId,
        public readonly string $subjectName,
        public readonly string $subjectEmail,
        public readonly AuthEventType $event,
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
        $what = match ($this->event) {
            AuthEventType::RecoveryCodeUsed => 'entró con un código de recuperación',
            AuthEventType::EmailBackupUsed => 'entró con el código enviado a su correo de respaldo',
            AuthEventType::EmailBackupRequested => 'pidió un código a su correo de respaldo para entrar sin su app de autenticación',
            AuthEventType::EmailBackupChanged => 'cambió su correo de respaldo',
            default => 'usó un método de respaldo de la verificación en dos pasos',
        };

        $self = $notifiable->id === $this->subjectUserId;

        return (new MailMessage)
            ->subject('Alerta de seguridad en AlertPrompt')
            ->greeting("Hola, {$notifiable->name}")
            ->line(($self ? 'Tu usuario' : "{$this->subjectName} ({$this->subjectEmail})")." {$what}.")
            ->line("Fecha: {$this->occurredAt}".($this->ip !== null ? " · IP: {$this->ip}" : ''))
            ->line($self
                ? 'Si no fuiste tú, cambia tu contraseña de inmediato y avisa al administrador de tu cuenta.'
                : 'Si no lo esperabas, confirma con la persona y, si hace falta, desactiva su usuario.');
    }
}
