<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use App\Models\PaymentMethod;
use App\Models\Tenant;

/** Pasarela de pagos con tarjeta. Solo maneja tokens, nunca números de tarjeta. */
interface PaymentGateway
{
    public function supportsCards(): bool;

    /**
     * Registra la tarjeta tokenizada en el navegador por la pasarela.
     *
     * @param  array{token: string, brand?: ?string, last4?: ?string, exp_month?: ?int, exp_year?: ?int}  $card
     */
    public function attachCard(Tenant $tenant, array $card): PaymentMethod;

    /** Cobra un monto; devuelve la referencia del cargo en la pasarela. */
    public function charge(PaymentMethod $method, string $amount, string $description): string;
}
