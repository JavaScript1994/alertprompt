<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Memberships;

use App\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::exists('plans', 'key')->where(fn ($query) => $query->where('is_active', true))],
            'billing_cycle' => ['required', new Enum(BillingCycle::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'starts_at' => ['required', 'date_format:Y-m-d'],
            'ends_at' => ['required', 'date_format:Y-m-d', 'after:starts_at'],
            'quotas' => ['nullable', 'array'],
            'quotas.whatsapp' => ['nullable', 'integer', 'min:0'],
            'quotas.sms' => ['nullable', 'integer', 'min:0'],
            'quotas.email' => ['nullable', 'integer', 'min:0'],
            'contract_reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['plan.exists' => 'El plan no existe o está desactivado.'];
    }

    public function attributes(): array
    {
        return [
            'plan' => 'plan', 'billing_cycle' => 'ciclo', 'price' => 'precio',
            'starts_at' => 'inicio', 'ends_at' => 'fin', 'contract_reference' => 'referencia del contrato',
        ];
    }
}
