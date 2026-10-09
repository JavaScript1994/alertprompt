<?php

declare(strict_types=1);

namespace App\Services\Billing\EInvoicing;

use App\Enums\SunatStatus;
use App\Models\Invoice;

/**
 * Sin proveedor de facturación electrónica: el comprobante queda como
 * documento interno (proforma) y NO se informa a SUNAT.
 */
class NoEInvoicing implements EInvoicingDriver
{
    public function send(Invoice $invoice): EInvoiceResult
    {
        return new EInvoiceResult(SunatStatus::NotSent);
    }
}
