<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\BulkImports\StoreBulkImportRequest;
use App\Http\Resources\BulkImportResource;
use App\Jobs\ImportBulkContacts;
use App\Models\BulkImport;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BulkImportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $imports = BulkImport::query()
            ->with(['tenant', 'uploader'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('tenant_id', $request->integer('client_id')))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return BulkImportResource::collection($imports);
    }

    public function show(BulkImport $bulkImport): BulkImportResource
    {
        return new BulkImportResource($bulkImport->load(['tenant', 'uploader']));
    }

    public function store(StoreBulkImportRequest $request, AuditLogger $audit): JsonResponse
    {
        $file = $request->file('file');

        // El archivo se conserva como evidencia del origen de la base.
        $import = BulkImport::query()->create([
            'tenant_id' => $request->integer('client_id'),
            'uploaded_by' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $file->store('bulk-imports', 'local'),
            'declared_source' => $request->string('declared_source')->toString(),
            'attestation_text' => BulkImport::ATTESTATION,
            'status' => 'queued',
        ]);

        $audit->record('bulk_import.uploaded', $import->tenant_id, $import, [
            'file' => $import->original_filename,
            'declared_source' => $import->declared_source,
        ]);

        ImportBulkContacts::dispatch($import->id);

        return (new BulkImportResource($import->load(['tenant', 'uploader'])))->response()->setStatusCode(202);
    }

    /** Texto de la declaración, para mostrarlo tal cual en el formulario. */
    public function attestation(): JsonResponse
    {
        return response()->json(['data' => ['text' => BulkImport::ATTESTATION]]);
    }
}
