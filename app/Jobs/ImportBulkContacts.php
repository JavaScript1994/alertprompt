<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Channel;
use App\Models\BulkImport;
use App\Models\Consent;
use App\Models\Contact;
use App\Services\AuditLogger;
use App\Services\BulkImports\BulkContactRow;
use App\Services\SuppressionService;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Procesa una carga masiva de la plataforma para un cliente. Importa los
 * contactos y registra consentimiento SOLO con evidencia completa por fila,
 * y nunca por encima de una baja o revocación del contacto (Ley 32323).
 */
class ImportBulkContacts implements ShouldQueue
{
    use Queueable;

    private const MAX_ERRORS = 200;

    public int $timeout = 1800;

    /** @var list<array{line: int, message: string}> */
    private array $errors = [];

    public function __construct(public readonly int $bulkImportId) {}

    public function handle(SuppressionService $suppressions, AuditLogger $audit): void
    {
        $import = BulkImport::query()->find($this->bulkImportId);

        if ($import === null || $import->status !== 'queued') {
            return;
        }

        $import->update(['status' => 'processing', 'started_at' => now()]);
        TenantContext::set($import->tenant_id);

        $stream = Storage::disk('local')->readStream($import->path);
        if (! is_resource($stream)) {
            $import->update(['status' => 'failed', 'finished_at' => now(), 'errors' => [['line' => 0, 'message' => 'No se pudo abrir el archivo.']]]);

            return;
        }

        $totals = ['rows' => 0, 'created' => 0, 'updated' => 0, 'consents_recorded' => 0, 'without_consent' => 0, 'consents_blocked' => 0, 'invalid' => 0];
        $header = null;
        $line = 0;

        $rows = LazyCollection::make(function () use ($stream) {
            while (($row = fgetcsv($stream, escape: '')) !== false) {
                yield $row;
            }
            fclose($stream);
        });

        foreach ($rows as $raw) {
            $line++;

            if ($header === null) {
                // Quita BOM de Excel y normaliza nombres de columna.
                $header = array_map(fn ($column) => Str::of((string) $column)->replace("\u{FEFF}", '')->trim()->lower()->toString(), $raw);

                continue;
            }

            if ($raw === [null]) {
                continue; // línea en blanco
            }

            $totals['rows']++;
            $data = count($raw) === count($header) ? array_combine($header, $raw) : false;

            if ($data === false) {
                $totals['invalid']++;
                $this->error($line, 'La fila no tiene la misma cantidad de columnas que el encabezado.');

                continue;
            }

            $row = BulkContactRow::fromCsv($data);

            if (($problem = $row->contactProblem()) !== null) {
                $totals['invalid']++;
                $this->error($line, $problem);

                continue;
            }

            [$contact, $wasCreated] = $this->upsertContact($row);
            $totals[$wasCreated ? 'created' : 'updated']++;

            if ($row->consentProblem !== null) {
                $this->error($line, "Contacto importado sin consentimiento: {$row->consentProblem}");
            }

            if ($row->consentChannels === []) {
                $totals['without_consent']++;

                continue;
            }

            foreach ($row->consentChannels as $channel) {
                $outcome = $this->recordConsent($import, $contact, $row, $channel, $suppressions);

                if ($outcome === 'recorded') {
                    $totals['consents_recorded']++;
                } elseif ($outcome !== 'already_active') {
                    $totals['consents_blocked']++;
                    $this->error($line, $outcome);
                }
            }
        }

        $import->update([...$totals, 'errors' => $this->errors, 'status' => 'completed', 'finished_at' => now()]);

        $audit->record('bulk_import.completed', $import->tenant_id, $import, $totals);
    }

    public function failed(Throwable $exception): void
    {
        BulkImport::query()->whereKey($this->bulkImportId)->update([
            'status' => 'failed',
            'finished_at' => now(),
            'errors' => json_encode([['line' => 0, 'message' => 'Error inesperado al procesar el archivo.']]),
        ]);
    }

    /** @return array{0: Contact, 1: bool} */
    private function upsertContact(BulkContactRow $row): array
    {
        $existing = Contact::query()
            ->where(function ($query) use ($row) {
                if ($row->phone !== null) {
                    $query->orWhere('phone', $row->phone);
                }
                if ($row->email !== null) {
                    $query->orWhere('email', $row->email);
                }
            })
            ->first();

        if ($existing !== null) {
            $existing->update([
                'name' => $row->name,
                'phone' => $row->phone ?? $existing->phone,
                'email' => $row->email ?? $existing->email,
                'attributes' => array_merge($existing->attributes ?? [], $row->attributes),
            ]);

            return [$existing, false];
        }

        return [Contact::query()->create([
            'name' => $row->name,
            'phone' => $row->phone,
            'email' => $row->email,
            'attributes' => $row->attributes,
        ]), true];
    }

    /** @return 'recorded'|'already_active'|string  string = motivo de bloqueo */
    private function recordConsent(BulkImport $import, Contact $contact, BulkContactRow $row, Channel $channel, SuppressionService $suppressions): string
    {
        $identifier = $row->identifierFor($channel);

        if ($identifier === null) {
            return "Consentimiento de {$channel->label()} sin ".($channel === Channel::Email ? 'email' : 'teléfono').'.';
        }

        // La baja del contacto manda sobre cualquier base, por vieja o nueva que sea.
        if ($suppressions->isSuppressed($import->tenant_id, $channel, $identifier)) {
            return "{$identifier} está dado de baja en {$channel->label()}: no se registra consentimiento.";
        }

        $revokedAfter = Consent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', $channel->value)
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '>=', $row->consentGrantedAt)
            ->exists();

        if ($revokedAfter) {
            return "{$identifier} revocó su consentimiento de {$channel->label()} después de la fecha indicada.";
        }

        if ($contact->hasConsentFor($channel->value)) {
            return 'already_active';
        }

        Consent::query()->create([
            'contact_id' => $contact->id,
            'channel' => $channel->value,
            'granted_at' => $row->consentGrantedAt,
            'source' => Str::limit("carga_masiva#{$import->id}: {$row->consentSource}", 250, ''),
            'ip' => $row->consentIp,
            'evidence_text' => $row->consentEvidence,
        ]);

        return 'recorded';
    }

    private function error(int $line, string $message): void
    {
        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = ['line' => $line, 'message' => $message];
        }
    }
}
