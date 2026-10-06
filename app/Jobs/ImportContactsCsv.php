<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class ImportContactsCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $path,
    ) {}

    public function handle(): void
    {
        app()->instance('current_tenant_id', $this->tenantId);

        $stream = Storage::disk('local')->readStream($this->path);

        if ($stream === null || $stream === false) {
            Log::warning('ImportContactsCsv: no se pudo abrir el archivo.', ['path' => $this->path]);

            return;
        }

        $rows = LazyCollection::make(function () use ($stream) {
            while (($row = fgetcsv($stream)) !== false) {
                yield $row;
            }

            fclose($stream);
        });

        $header = null;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if ($header === null) {
                $header = array_map(
                    fn (string $column) => Str::of($column)->trim()->lower()->toString(),
                    $row,
                );

                continue;
            }

            $data = array_combine($header, $row);

            if ($data === false) {
                $skipped++;

                continue;
            }

            $name = trim((string) ($data['name'] ?? ''));
            $phone = trim((string) ($data['phone'] ?? '')) ?: null;
            $email = trim((string) ($data['email'] ?? '')) ?: null;

            if ($name === '' || ($phone === null && $email === null)) {
                $skipped++;

                continue;
            }

            $attributes = Arr::except($data, ['name', 'phone', 'email']);

            $existing = Contact::query()
                ->where(function ($query) use ($phone, $email) {
                    if ($phone !== null) {
                        $query->orWhere('phone', $phone);
                    }

                    if ($email !== null) {
                        $query->orWhere('email', $email);
                    }
                })
                ->first();

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'phone' => $phone ?? $existing->phone,
                    'email' => $email ?? $existing->email,
                    'attributes' => array_merge($existing->attributes ?? [], $attributes),
                ]);
                $updated++;
            } else {
                Contact::create([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'attributes' => $attributes,
                ]);
                $created++;
            }
        }

        Storage::disk('local')->delete($this->path);

        Log::info('ImportContactsCsv completado.', [
            'tenant_id' => $this->tenantId,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}
