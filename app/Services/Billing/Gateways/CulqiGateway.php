<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use App\Exceptions\NotImplementedException;
use App\Models\PaymentMethod;
use App\Models\Tenant;

/**
 * Culqi. Pendiente: requiere cuenta de comercio, llaves pública/privada y
 * Culqi Checkout en el frontend para tokenizar (la tarjeta nunca toca
 * nuestro servidor).
 */
class CulqiGateway implements PaymentGateway
{
    public function supportsCards(): bool
    {
        return true;
    }

    public function attachCard(Tenant $tenant, array $card): PaymentMethod
    {
        throw new NotImplementedException('CulqiGateway no está implementado todavía. Usa BILLING_GATEWAY=manual.');
    }

    public function charge(PaymentMethod $method, string $amount, string $description): string
    {
        throw new NotImplementedException('CulqiGateway no está implementado todavía.');
    }
}
