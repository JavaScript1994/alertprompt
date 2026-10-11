<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Notas de crédito de todos los clientes (sin TenantScope a propósito). */
class CreditNoteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['client_id' => ['nullable', 'integer']]);

        return CreditNoteResource::collection($this->query()
            ->with(['invoice', 'applications', 'tenant'])
            ->when($request->filled('client_id'), fn (Builder $q) => $q->where('tenant_id', $request->integer('client_id')))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 100)));
    }

    public function show(int $creditNote): CreditNoteResource
    {
        return new CreditNoteResource($this->query()->with(['invoice', 'applications', 'tenant'])->findOrFail($creditNote));
    }

    /** @return Builder<CreditNote> */
    private function query(): Builder
    {
        return CreditNote::query()->withoutGlobalScope(TenantScope::class);
    }
}
