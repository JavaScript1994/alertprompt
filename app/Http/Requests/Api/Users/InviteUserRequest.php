<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo', 'role_id' => 'rol'];
    }
}
