<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Billing;

use Illuminate\Foundation\Http\FormRequest;

/** Solo el token de la pasarela y datos no sensibles (marca, últimos 4). */
class AddPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:20'],
            'last4' => ['nullable', 'digits:4'],
            'exp_month' => ['nullable', 'integer', 'between:1,12'],
            'exp_year' => ['nullable', 'integer', 'min:2024'],
        ];
    }
}
