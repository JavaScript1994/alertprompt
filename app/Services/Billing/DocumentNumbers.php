<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;

/** Correlativo por serie (comprobantes y notas de crédito). Llamar dentro de una transacción. */
final class DocumentNumbers
{
    public function next(string $series): int
    {
        DB::table('invoice_sequences')->insertOrIgnore(['series' => $series, 'last_number' => 0]);
        $number = (int) DB::table('invoice_sequences')->where('series', $series)->lockForUpdate()->value('last_number') + 1;
        DB::table('invoice_sequences')->where('series', $series)->update(['last_number' => $number]);

        return $number;
    }
}
