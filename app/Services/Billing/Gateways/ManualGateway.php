<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/** Sin pasarela: los pagos los registra la plataforma (transferencia, Yape…). */
class ManualGateway implements PaymentGateway
{
    public function supportsCards(): bool
    {
        return false;
    }

    public function attachCard(Tenant $tenant, array $card): PaymentMethod
    {
        throw ValidationException::withMessages([
            'token' => 'El pago con tarjeta aún no está disponible. Paga por transferencia, Yape o Plin.',
        ]);
    }

    public function charge(PaymentMethod $method, string $amount, string $description): string
    {
        throw ValidationException::withMessages(['payment' => 'No hay pasarela de pagos configurada.']);
    }
}
