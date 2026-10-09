<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Billing;

use App\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            // Tarjeta solo la registra la pasarela, no a mano.
            'method' => ['required', Rule::in(array_map(fn ($m) => $m->value, array_filter(PaymentMethodType::cases(), fn ($m) => $m !== PaymentMethodType::Card)))],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'monto', 'method' => 'medio de pago', 'paid_at' => 'fecha de pago', 'reference' => 'número de operación'];
    }
}
