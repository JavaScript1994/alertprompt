<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Clients;

use App\Enums\DocumentType;
use App\Enums\TenantType;
use App\Http\Requests\Api\Users\PersonalDataRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_number' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('document_number'))),
            'admin_email' => mb_strtolower(trim((string) $this->input('admin_email'))),
        ]);
    }

    public function rules(): array
    {
        $type = TenantType::tryFrom((string) $this->input('type'));
        $documentType = DocumentType::tryFrom((string) $this->input('document_type'));

        return [
            'type' => ['required', new Enum(TenantType::class)],
            ...ClientRules::profile($type, $documentType),
            'plan' => ['sometimes', Rule::exists('plans', 'key')->where(fn ($query) => $query->where('is_active', true))],
            ...PersonalDataRules::rules('admin_'),
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:users,pending_email'],
            'admin_photo' => ['nullable', ...PersonalDataRules::photo()],
        ];
    }

    public function attributes(): array
    {
        return [...ClientRules::attributes(), ...PersonalDataRules::attributes('admin_')];
    }

    public function messages(): array
    {
        return PersonalDataRules::messages('admin_');
    }
}
