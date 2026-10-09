<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\InvoiceManager;
use Illuminate\Console\Command;

class RunDailyBilling extends Command
{
    protected $signature = 'billing:daily';

    protected $description = 'Emite los comprobantes de membresías del período y alerta los vencidos.';

    public function handle(InvoiceManager $invoices): int
    {
        $result = $invoices->runDaily();

        $this->info("Comprobantes emitidos: {$result['issued']} · vencidos: {$result['overdue']}");

        return self::SUCCESS;
    }
}
