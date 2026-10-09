<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\ChannelAccounts;

use App\Enums\ChannelAccountStatus;
use App\Enums\QualityRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ConfigureChannelAccountRequest extends FormRequest
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
        $channel = (string) $this->route('channel');

        return [
            'provider' => ['required', Rule::in(config("channels.tenant_accounts.{$channel}", []))],
            'sender' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'display_name' => ['nullable', 'string', 'max:80'],
            'status' => ['required', new Enum(ChannelAccountStatus::class)],
            'quality_rating' => ['nullable', new Enum(QualityRating::class)],
            'messaging_tier' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['sender.regex' => 'Usa el formato internacional, por ejemplo +51987654321.'];
    }
}
