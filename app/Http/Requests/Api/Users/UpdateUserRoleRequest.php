<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['role_id' => ['required', 'integer']];
    }

    public function attributes(): array
    {
        return ['role_id' => 'rol'];
    }
}
