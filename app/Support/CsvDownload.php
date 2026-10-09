<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvDownload
{
    /** @param  list<array<string, mixed>>  $campaigns */
    public static function campaigns(array $campaigns, string $filename, bool $includeTenant): StreamedResponse
    {
        $headers = [
            ...($includeTenant ? ['Cliente'] : []),
            'Campaña', 'Canal', 'Categoría', 'Estado', 'Intentos', 'Enviados', 'Entregados', 'Leídos', 'Fallidos', 'Omitidos', '% entrega',
        ];

        return response()->streamDownload(function () use ($campaigns, $headers, $includeTenant) {
            $out = fopen('php://output', 'w');
            // BOM: Excel abre el UTF-8 con tildes correctamente.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, escape: '');

            foreach ($campaigns as $row) {
                fputcsv($out, [
                    ...($includeTenant ? [$row['tenant_name']] : []),
                    $row['name'], $row['channel'], $row['category'], $row['status'],
                    $row['attempted'], $row['sent'], $row['delivered'], $row['read'], $row['failed'], $row['skipped'],
                    $row['delivery_rate'],
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
