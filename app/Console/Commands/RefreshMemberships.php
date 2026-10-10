<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Memberships\MembershipManager;
use Illuminate\Console\Command;

class RefreshMemberships extends Command
{
    protected $signature = 'memberships:refresh';

    protected $description = 'Activa las membresías que empiezan hoy y renueva las que terminaron sin sucesora.';

    public function handle(MembershipManager $memberships): int
    {
        $result = $memberships->refresh();

        $this->info("Membresías activadas: {$result['activated']} · vencidas y renovadas: {$result['expired']}");

        return self::SUCCESS;
    }
}
