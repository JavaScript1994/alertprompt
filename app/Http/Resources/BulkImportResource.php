<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BulkImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BulkImport
 */
class BulkImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant' => $this->tenant ? ['id' => $this->tenant->id, 'name' => $this->tenant->name] : null,
            'uploaded_by' => $this->uploader?->name,
            'original_filename' => $this->original_filename,
            'declared_source' => $this->declared_source,
            'attestation_text' => $this->attestation_text,
            'status' => $this->status,
            'totals' => [
                'rows' => $this->rows,
                'created' => $this->created,
                'updated' => $this->updated,
                'consents_recorded' => $this->consents_recorded,
                'without_consent' => $this->without_consent,
                'consents_blocked' => $this->consents_blocked,
                'invalid' => $this->invalid,
            ],
            'errors' => $this->when($request->route('bulkImport') !== null, fn () => $this->errors ?? []),
            'errors_count' => count($this->errors ?? []),
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
