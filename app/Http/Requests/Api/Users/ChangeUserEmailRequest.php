<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

class ChangeUserEmailRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:users,pending_email'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'correo'];
    }
}
