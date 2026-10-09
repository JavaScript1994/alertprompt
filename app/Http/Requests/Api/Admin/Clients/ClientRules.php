<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Clients;

use App\Enums\DocumentType;
use App\Enums\TenantType;
use App\Support\PeruvianDocument;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Reglas compartidas por alta y edición de clientes. */
final class ClientRules
{
    /** @return array<string, list<mixed>> */
    public static function profile(?TenantType $type, ?DocumentType $documentType, ?int $ignoreTenantId = null): array
    {
        $allowedDocuments = $type === null ? [] : array_map(fn (DocumentType $d) => $d->value, DocumentType::allowedFor($type));

        return [
            'name' => ['required', 'string', 'max:160'],
            'document_type' => ['required', new Enum(DocumentType::class), Rule::in($allowedDocuments)],
            'document_number' => [
                'required', 'string', 'max:20',
                Rule::unique('tenants', 'document_number')->ignore($ignoreTenantId),
                function (string $attribute, mixed $value, Closure $fail) use ($type, $documentType) {
                    if ($documentType !== null && ! PeruvianDocument::isValid($documentType, (string) $value, $type)) {
                        $fail(match ($documentType) {
                            DocumentType::Ruc => $type === TenantType::Company
                                ? 'El RUC de una empresa empieza con 20 y debe ser válido (11 dígitos).'
                                : 'El RUC de una persona natural empieza con 10, 15 o 17 y debe ser válido.',
                            DocumentType::Dni => 'El DNI tiene 8 dígitos.',
                            DocumentType::Ce => 'El carné de extranjería tiene entre 9 y 12 caracteres.',
                        });
                    }
                },
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'name' => 'nombre o razón social',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'contact_email' => 'correo de contacto',
            'contact_phone' => 'teléfono de contacto',
            'admin_name' => 'nombre del administrador',
            'admin_email' => 'correo del administrador',
        ];
    }
}
