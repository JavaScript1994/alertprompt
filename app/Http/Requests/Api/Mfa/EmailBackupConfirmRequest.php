<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mfa;

use Illuminate\Foundation\Http\FormRequest;

class EmailBackupConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
