<?php

declare(strict_types=1);

namespace App\Services\Billing\EInvoicing;

use App\Exceptions\NotImplementedException;
use App\Models\Invoice;

/**
 * Nubefact (PSE/OSE). Pendiente: requiere cuenta, RUC emisor habilitado como
 * emisor electrónico y token de la API. Mismo criterio que WhatsAppCloudDriver.
 */
class NubefactEInvoicing implements EInvoicingDriver
{
    public function send(Invoice $invoice): EInvoiceResult
    {
        throw new NotImplementedException('NubefactEInvoicing no está implementado todavía. Usa BILLING_EINVOICE_DRIVER=none.');
    }
}
