<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Templates;

use Illuminate\Foundation\Http\FormRequest;

class PreviewTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4096'],
            'sample_data' => ['nullable', 'array'],
            'sample_data.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
