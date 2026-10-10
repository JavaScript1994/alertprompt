<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mfa;

use App\Enums\SensitiveAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReauthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(SensitiveAction::class)],
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
