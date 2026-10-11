<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

/** Datos personales (Mi perfil o Usuarios → Editar). El correo y el rol van aparte. */
class UpdatePersonalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PersonalDataRules::rules();
    }

    public function attributes(): array
    {
        return PersonalDataRules::attributes();
    }

    public function messages(): array
    {
        return PersonalDataRules::messages();
    }
}
