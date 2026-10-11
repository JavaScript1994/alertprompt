<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Settings;

use App\Rules\ReadableBrandColor;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // null = color de AlertPrompt.
            'brand_color' => ['nullable', 'string', new ReadableBrandColor],
            'brand_title' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function attributes(): array
    {
        return ['brand_color' => 'color', 'brand_title' => 'título de bienvenida'];
    }
}
