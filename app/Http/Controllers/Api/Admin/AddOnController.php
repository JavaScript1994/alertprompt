<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TenantAddOnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AddOns\StoreAddOnRequest;
use App\Http\Requests\Api\Admin\AddOns\UpdateAddOnRequest;
use App\Http\Resources\AddOnResource;
use App\Models\AddOn;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Catálogo de servicios adicionales. No se borran (los referencian contratos
 * y solicitudes): se desactivan. Desactivar uno no cancela los contratos
 * vigentes; solo deja de ofrecerse.
 */
class AddOnController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        return AddOnResource::collection($this->withCounts(AddOn::query())->orderByDesc('is_active')->orderBy('sort')->get());
    }

    public function store(StoreAddOnRequest $request): JsonResponse
    {
        $addOn = new AddOn([
            ...$this->attributes($request->validated()),
            'key' => $this->uniqueKey($request->string('name')->toString()),
            'is_active' => true,
            'sort' => (int) AddOn::query()->max('sort') + 1,
        ]);
        $addOn->save();

        $this->audit->record('add_on.created', subject: $addOn, metadata: ['add_on' => $addOn->key]);

        return (new AddOnResource($this->fresh($addOn)))->response()->setStatusCode(201);
    }

    public function update(UpdateAddOnRequest $request, AddOn $addOn): AddOnResource
    {
        $addOn->fill($this->attributes($request->validated()));
        $changes = array_keys($addOn->getDirty());
        $addOn->save();

        if ($changes !== []) {
            $this->audit->record('add_on.updated', subject: $addOn, metadata: ['add_on' => $addOn->key, 'fields' => $changes]);
        }

        return new AddOnResource($this->fresh($addOn));
    }

    public function deactivate(AddOn $addOn): AddOnResource
    {
        $addOn->update(['is_active' => false]);
        $this->audit->record('add_on.deactivated', subject: $addOn, metadata: ['add_on' => $addOn->key]);

        return new AddOnResource($this->fresh($addOn));
    }

    public function activate(AddOn $addOn): AddOnResource
    {
        $addOn->update(['is_active' => true]);
        $this->audit->record('add_on.activated', subject: $addOn, metadata: ['add_on' => $addOn->key]);

        return new AddOnResource($this->fresh($addOn));
    }

    /** @param  array<string, mixed>  $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'module' => $data['module'] ?? null,
            'monthly_price' => $data['monthly_price'] ?? null,
        ];
    }

    /** Clave estable derivada del nombre: "Chat en vivo" → "chat-en-vivo" (o "chat-en-vivo-2"). */
    private function uniqueKey(string $name): string
    {
        $base = Str::limit(Str::slug($name), 34, '') ?: 'servicio';
        $key = $base;
        $suffix = 2;

        while (AddOn::query()->where('key', $key)->exists()) {
            $key = "{$base}-{$suffix}";
            $suffix++;
        }

        return $key;
    }

    /** Clientes con el servicio contratado hoy y clientes interesados. */
    private function withCounts(Builder $query): Builder
    {
        return $query->withCount([
            'contracts as active_contracts_count' => fn (Builder $q) => $q->where('status', TenantAddOnStatus::Active),
            'interests',
        ]);
    }

    private function fresh(AddOn $addOn): AddOn
    {
        return $this->withCounts(AddOn::query())->findOrFail($addOn->id);
    }
}
