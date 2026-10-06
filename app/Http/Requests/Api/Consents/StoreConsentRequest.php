<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Consents;

use App\Enums\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', new Enum(Channel::class)],
            'source' => ['required', 'string', 'max:255'],
            // Ley N° 32323: la evidencia debe guardar el texto exacto que el
            // contacto aceptó, no una descripción genérica.
            'evidence_text' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Elegí un canal.',
            'source.required' => 'Indicá el origen del consentimiento.',
            'evidence_text.required' => 'Ingresá el texto exacto que aceptó el contacto.',
        ];
    }
}
