<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\AddOns;

use App\Enums\AddOnStatus;
use Illuminate\Validation\Rule;

/** Reglas comunes de alta y edición de servicios adicionales. */
final class AddOnRules
{
    /** @return array<string, list<mixed>> */
    public static function rules(?int $ignoreId = null): array
    {
        $available = 'status,'.AddOnStatus::Available->value;

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('add_ons', 'name')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(AddOnStatus::class)],
            // Uno disponible prende un módulo y tiene precio; uno próximo, ninguno de los dos.
            'module' => [
                "required_if:{$available}", 'prohibited_unless:'.$available, 'nullable', 'string',
                Rule::in(array_keys(config('modules.catalog'))),
                Rule::unique('add_ons', 'module')->ignore($ignoreId),
            ],
            'monthly_price' => ["required_if:{$available}", 'nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return ['name' => 'nombre', 'description' => 'descripción', 'status' => 'estado', 'module' => 'módulo', 'monthly_price' => 'precio mensual'];
    }
}
