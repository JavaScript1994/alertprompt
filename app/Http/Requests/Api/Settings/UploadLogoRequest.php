<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Settings;

use Illuminate\Foundation\Http\FormRequest;

/** Sin SVG: puede llevar scripts y el logo se sirve en una ruta pública. */
class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = (int) config('branding.logo.min_px');
        $max = (int) config('branding.logo.max_px');

        return [
            'logo' => [
                'required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.(int) config('branding.logo.max_kb'),
                "dimensions:min_width={$min},min_height={$min},max_width={$max},max_height={$max}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.mimes' => 'Usa un logo PNG, JPG o WebP (SVG no está permitido).',
            'logo.dimensions' => 'El logo debe medir entre '.config('branding.logo.min_px').' y '.config('branding.logo.max_px').' píxeles por lado.',
        ];
    }
}
