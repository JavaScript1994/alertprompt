<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mfa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EmailBackupSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // El respaldo sirve si pierdes el acceso a tu cuenta principal:
                // el mismo correo de login no aporta nada.
                if (strtolower(trim((string) $this->input('email'))) === strtolower((string) $this->user()?->email)) {
                    $validator->errors()->add('email', 'Usa un correo distinto al de tu usuario.');
                }
            },
        ];
    }
}
