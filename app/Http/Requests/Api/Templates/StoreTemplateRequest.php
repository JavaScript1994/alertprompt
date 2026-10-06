<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Templates;

use App\Enums\Channel;
use App\Enums\TemplateCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'channel' => ['required', new Enum(Channel::class)],
            'category' => ['required', new Enum(TemplateCategory::class)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('templates')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'body' => ['required', 'string', 'max:4096'],
        ];
    }
}
