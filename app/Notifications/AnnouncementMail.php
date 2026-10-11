<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\User;
use App\Support\TenantDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Correo de un comunicado operativo (incidente o acción requerida). */
class AnnouncementMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $announcementId) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $announcement = Announcement::query()->findOrFail($this->announcementId);
        $base = TenantDomain::linkBaseFor($notifiable->tenant);
        $link = $announcement->link_url !== null && Str::startsWith($announcement->link_url, '/')
            ? $base.$announcement->link_url
            : ($announcement->link_url ?? $base.'/announcements');

        $message = (new MailMessage)
            ->subject("[{$announcement->type->label()}] {$announcement->title}")
            ->greeting("Hola, {$notifiable->name}");

        foreach (preg_split('/\R{2,}/', trim($announcement->body)) ?: [] as $paragraph) {
            $message->line($paragraph);
        }

        return $message->action($announcement->link_label ?? 'Ver en AlertPrompt', $link);
    }
}
