<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que el cliente puede editar de su cuenta. El tipo y el documento
 * (RUC/DNI) solo los cambia la plataforma: definen la facturación.
 */
class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'contact_email' => 'correo de contacto',
            'contact_phone' => 'teléfono',
            'address' => 'dirección',
            'timezone' => 'zona horaria',
        ];
    }
}
