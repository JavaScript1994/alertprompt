<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mfa;

use Illuminate\Foundation\Http\FormRequest;

class EmailBackupVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'max:2048'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
