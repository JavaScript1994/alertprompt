<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentType;
use App\Enums\TenantType;

/**
 * Validación de documentos de identidad peruanos. El RUC se valida con su
 * dígito verificador (módulo 11), como lo hace SUNAT.
 */
final class PeruvianDocument
{
    private const RUC_WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public static function isValid(DocumentType $type, string $number, ?TenantType $tenantType = null): bool
    {
        return match ($type) {
            DocumentType::Dni => (bool) preg_match('/^\d{8}$/', $number),
            DocumentType::Ce => (bool) preg_match('/^[A-Z0-9]{9,12}$/', $number),
            DocumentType::Ruc => self::isValidRuc($number, $tenantType),
        };
    }

    public static function isValidRuc(string $ruc, ?TenantType $tenantType = null): bool
    {
        if (! preg_match('/^(10|15|17|20)\d{9}$/', $ruc)) {
            return false;
        }

        // RUC 20 = persona jurídica; 10/15/17 = persona natural.
        if ($tenantType === TenantType::Company && ! str_starts_with($ruc, '20')) {
            return false;
        }
        if ($tenantType === TenantType::Individual && str_starts_with($ruc, '20')) {
            return false;
        }

        $sum = 0;
        foreach (self::RUC_WEIGHTS as $i => $weight) {
            $sum += (int) $ruc[$i] * $weight;
        }

        $check = 11 - ($sum % 11);
        $check = match ($check) {
            10 => 0,
            11 => 1,
            default => $check,
        };

        return $check === (int) $ruc[10];
    }
}
