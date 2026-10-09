<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

/** El correo no se edita: es la identidad de inicio de sesión. */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role_id' => ['required', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'role_id' => 'rol'];
    }
}
