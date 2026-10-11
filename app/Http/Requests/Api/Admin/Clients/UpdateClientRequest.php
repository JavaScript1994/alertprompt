<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Clients;

use App\Enums\DocumentType;
use App\Models\Tenant;
use App\Support\TenantSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** El tipo (empresa / persona natural) no cambia: define facturación y documentos. */
class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_number' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('document_number'))),
        ]);

        if ($this->has('slug')) {
            $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
        }
    }

    public function rules(): array
    {
        /** @var Tenant $client */
        $client = $this->route('client');

        return [
            ...ClientRules::profile($client->type, DocumentType::tryFrom((string) $this->input('document_type')), $client->id),
            // Cambiarlo rompe los enlaces que la empresa ya compartió: solo la plataforma lo edita.
            'slug' => [
                'sometimes', 'string', 'regex:'.TenantSlug::PATTERN,
                Rule::notIn((array) config('branding.reserved_slugs', [])),
                Rule::unique('tenants', 'slug')->ignore($client->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [...ClientRules::attributes(), 'slug' => 'subdominio'];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Usa de 3 a 40 letras minúsculas, números o guiones (sin guion al inicio ni al final).',
            'slug.not_in' => 'Ese subdominio está reservado.',
        ];
    }
}
