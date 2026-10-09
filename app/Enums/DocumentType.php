<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case Ruc = 'ruc';
    case Dni = 'dni';
    case Ce = 'ce';

    public function label(): string
    {
        return match ($this) {
            DocumentType::Ruc => 'RUC',
            DocumentType::Dni => 'DNI',
            DocumentType::Ce => 'Carné de extranjería',
        };
    }

    /** Documentos válidos para cada tipo de cliente. */
    public static function allowedFor(TenantType $type): array
    {
        return match ($type) {
            TenantType::Company => [DocumentType::Ruc],
            // Persona natural: DNI o CE; RUC 10 si tiene negocio propio.
            TenantType::Individual => [DocumentType::Dni, DocumentType::Ce, DocumentType::Ruc],
        };
    }
}
