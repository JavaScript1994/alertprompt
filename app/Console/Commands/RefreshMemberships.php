<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Memberships\MembershipManager;
use Illuminate\Console\Command;

class RefreshMemberships extends Command
{
    protected $signature = 'memberships:refresh';

    protected $description = 'Activa las membresías que empiezan hoy, vence las terminadas y avisa las que están por vencer.';

    public function handle(MembershipManager $memberships): int
    {
        $result = $memberships->refresh();

        $this->info("Membresías activadas: {$result['activated']} · vencidas: {$result['expired']}");

        return self::SUCCESS;
    }
}
