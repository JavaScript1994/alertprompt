<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\ChannelAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestChannelAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['sender' => preg_replace('/[\s\-()]/', '', (string) $this->input('sender'))]);
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(array_keys(config('channels.tenant_accounts')))],
            'sender' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'display_name' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return ['sender.regex' => 'Usa el formato internacional, por ejemplo +51987654321.'];
    }

    public function attributes(): array
    {
        return ['sender' => 'número', 'display_name' => 'nombre visible'];
    }
}
