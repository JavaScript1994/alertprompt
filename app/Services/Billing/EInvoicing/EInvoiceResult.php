<?php

declare(strict_types=1);

namespace App\Services\Billing\EInvoicing;

use App\Enums\SunatStatus;

final class EInvoiceResult
{
    public function __construct(
        public readonly SunatStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $pdfUrl = null,
    ) {}
}
