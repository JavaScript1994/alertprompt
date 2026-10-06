<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $contactId = $this->route('contact')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'required_without:email',
                'string',
                'max:32',
                Rule::unique('contacts')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($contactId),
            ],
            'email' => [
                'nullable',
                'required_without:phone',
                'email',
                'max:255',
                Rule::unique('contacts')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($contactId),
            ],
            'attributes' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre del contacto.',
            'phone.required_without' => 'Ingresa un teléfono o un email.',
            'phone.unique' => 'Ya existe un contacto con este teléfono.',
            'email.required_without' => 'Ingresa un teléfono o un email.',
            'email.email' => 'Ingresa un email válido.',
            'email.unique' => 'Ya existe un contacto con este email.',
        ];
    }
}
