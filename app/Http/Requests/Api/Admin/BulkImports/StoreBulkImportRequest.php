<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\BulkImports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('tenants', 'id')->where(fn ($query) => $query->where('is_platform', false))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'declared_source' => ['required', 'string', 'min:15', 'max:1000'],
            'attestation' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'declared_source.min' => 'Describe con más detalle de dónde salió la base (formulario, evento, campaña…).',
            'attestation.accepted' => 'Debes aceptar la declaración de consentimiento para subir la base.',
        ];
    }

    public function attributes(): array
    {
        return ['client_id' => 'cliente', 'file' => 'archivo', 'declared_source' => 'origen de la base'];
    }
}
