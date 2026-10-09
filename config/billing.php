<?php

declare(strict_types=1);

use App\Services\Billing\EInvoicing\NoEInvoicing;
use App\Services\Billing\EInvoicing\NubefactEInvoicing;
use App\Services\Billing\Gateways\CulqiGateway;
use App\Services\Billing\Gateways\ManualGateway;

return [
    'currency' => 'PEN',
    'igv_rate' => 0.18,
    'due_days' => (int) env('BILLING_DUE_DAYS', 7),

    // Series de comprobantes: factura (cliente con RUC) y boleta (DNI/CE).
    'series' => [
        'factura' => env('BILLING_SERIES_FACTURA', 'F001'),
        'boleta' => env('BILLING_SERIES_BOLETA', 'B001'),
    ],

    /*
    | Facturación electrónica (SUNAT) vía OSE/PSE. 'none' = los comprobantes
    | se emiten internamente y se muestran como proforma, no como comprobante
    | electrónico válido, hasta conectar un proveedor.
    */
    'einvoicing' => [
        'driver' => env('BILLING_EINVOICE_DRIVER', 'none'),
        'drivers' => [
            'none' => NoEInvoicing::class,
            'nubefact' => NubefactEInvoicing::class,
        ],
    ],

    /*
    | Pasarela de pagos con tarjeta. 'manual' = solo pagos registrados por la
    | plataforma (transferencia, Yape, Plin, efectivo). Nunca se guardan
    | números de tarjeta: solo el token que entrega la pasarela.
    */
    'gateway' => [
        'driver' => env('BILLING_GATEWAY', 'manual'),
        'drivers' => [
            'manual' => ManualGateway::class,
            'culqi' => CulqiGateway::class,
        ],
    ],

    // Datos para pagar por transferencia, visibles para el cliente.
    // Vacía en .env = texto por defecto.
    'transfer_instructions' => env('BILLING_TRANSFER_INSTRUCTIONS') ?: 'Transferencia o depósito a la cuenta que te indique AlertPrompt. Envía la constancia a facturacion@alertprompt.pe indicando el número de comprobante.',
];
