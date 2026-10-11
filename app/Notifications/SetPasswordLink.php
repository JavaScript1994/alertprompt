<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Support\TenantDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enlace para definir contraseña. Sirve para la invitación de un usuario
 * nuevo (dura 7 días) y para "olvidé mi contraseña" (dura 60 minutos).
 */
class SetPasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly bool $invitation = false,
        public readonly ?string $tenantName = null,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        // Al login con la marca de su empresa, si la tiene.
        $url = TenantDomain::linkBaseFor($notifiable->tenant).'/reset-password?'.http_build_query(array_filter([
            'token' => $this->token,
            'email' => $notifiable->email,
            'invite' => $this->invitation ? '1' : null,
        ]));

        if ($this->invitation) {
            return (new MailMessage)
                ->subject('Te invitaron a AlertPrompt')
                ->greeting("Hola, {$notifiable->name}")
                ->line($this->tenantName !== null
                    ? "Se creó tu acceso al panel de {$this->tenantName} en AlertPrompt."
                    : 'Se creó tu acceso a AlertPrompt.')
                ->action('Crear mi contraseña', $url)
                ->line('El enlace vence en 7 días. Si no esperabas este correo, puedes ignorarlo.');
        }

        return (new MailMessage)
            ->subject('Restablece tu contraseña de AlertPrompt')
            ->greeting("Hola, {$notifiable->name}")
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->action('Restablecer contraseña', $url)
            ->line('El enlace vence en 60 minutos. Si no lo pediste, ignora este correo: tu contraseña no cambia.');
    }
}
