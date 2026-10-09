<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Plans;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'monthly_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'quotas' => ['present', 'array'],
            'quotas.whatsapp' => ['nullable', 'integer', 'min:0'],
            'quotas.sms' => ['nullable', 'integer', 'min:0'],
            'quotas.email' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'monthly_price' => 'precio mensual', 'is_public' => 'visible para clientes'];
    }
}
