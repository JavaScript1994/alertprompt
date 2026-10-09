<?php

declare(strict_types=1);

namespace App\Services\Billing\EInvoicing;

use App\Models\Invoice;

/** Envío de comprobantes a SUNAT a través de un OSE/PSE. */
interface EInvoicingDriver
{
    public function send(Invoice $invoice): EInvoiceResult;
}
