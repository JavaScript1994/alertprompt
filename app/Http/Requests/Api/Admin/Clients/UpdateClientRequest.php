<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Clients;

use App\Enums\DocumentType;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

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
    }

    public function rules(): array
    {
        /** @var Tenant $client */
        $client = $this->route('client');

        return ClientRules::profile($client->type, DocumentType::tryFrom((string) $this->input('document_type')), $client->id);
    }

    public function attributes(): array
    {
        return ClientRules::attributes();
    }
}
