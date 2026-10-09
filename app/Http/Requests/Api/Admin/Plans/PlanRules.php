<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Plans;

use Illuminate\Validation\Rule;

/** Reglas comunes de alta y edición de planes. */
final class PlanRules
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'monthly_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'quotas' => ['present', 'array'],
            'quotas.whatsapp' => ['nullable', 'integer', 'min:0'],
            'quotas.sms' => ['nullable', 'integer', 'min:0'],
            'quotas.email' => ['nullable', 'integer', 'min:0'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', 'distinct', Rule::in(array_keys(config('modules.catalog')))],
            'is_public' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return ['name' => 'nombre', 'monthly_price' => 'precio mensual', 'modules' => 'módulos', 'is_public' => 'visible para clientes'];
    }
}
