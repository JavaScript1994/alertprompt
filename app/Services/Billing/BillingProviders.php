<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\Billing\EInvoicing\EInvoicingDriver;
use App\Services\Billing\Gateways\PaymentGateway;
use InvalidArgumentException;

/** Igual que ChannelManager: el único que conoce los proveedores de cobro. */
final class BillingProviders
{
    public function einvoicing(): EInvoicingDriver
    {
        return $this->resolve('einvoicing');
    }

    public function gateway(): PaymentGateway
    {
        return $this->resolve('gateway');
    }

    private function resolve(string $kind): object
    {
        $name = config("billing.{$kind}.driver");
        $class = config("billing.{$kind}.drivers.{$name}");

        if ($class === null) {
            throw new InvalidArgumentException("Proveedor de {$kind} no soportado: [{$name}].");
        }

        return app($class);
    }
}
