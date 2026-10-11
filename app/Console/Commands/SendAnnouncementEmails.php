<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Announcements\AnnouncementService;
use Illuminate\Console\Command;

class SendAnnouncementEmails extends Command
{
    protected $signature = 'announcements:send-emails';

    protected $description = 'Envía por correo los comunicados operativos ya publicados (incidentes y acciones requeridas).';

    public function handle(AnnouncementService $announcements): int
    {
        $this->info('Correos encolados: '.$announcements->sendDueEmails());

        return self::SUCCESS;
    }
}
