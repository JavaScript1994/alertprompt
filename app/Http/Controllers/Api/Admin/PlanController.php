<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Plans\StorePlanRequest;
use App\Http\Requests\Api\Admin\Plans\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogo de planes. Los planes no se borran (los referencian membresías y
 * comprobantes): se desactivan. Uno inactivo no se ofrece en altas nuevas.
 */
class PlanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection($this->withClientCounts(Plan::query())->orderByDesc('is_active')->orderBy('sort')->get());
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = new Plan([
            ...$this->attributes($request->validated()),
            'key' => $this->uniqueKey($request->string('name')->toString()),
            'is_active' => true,
            'sort' => (int) Plan::query()->max('sort') + 1,
        ]);
        $plan->save();

        $this->audit->record('plan.created', subject: $plan, metadata: ['plan' => $plan->key]);

        return (new PlanResource($this->fresh($plan)))->response()->setStatusCode(201);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        $plan->fill($this->attributes($request->validated()));
        $changes = array_keys($plan->getDirty());
        $plan->save();

        if ($changes !== []) {
            $this->audit->record('plan.updated', subject: $plan, metadata: ['plan' => $plan->key, 'fields' => $changes]);
        }

        return new PlanResource($this->fresh($plan));
    }

    public function deactivate(Plan $plan): PlanResource
    {
        $plan->update(['is_active' => false]);
        $this->audit->record('plan.deactivated', subject: $plan, metadata: ['plan' => $plan->key]);

        return new PlanResource($this->fresh($plan));
    }

    public function activate(Plan $plan): PlanResource
    {
        $plan->update(['is_active' => true]);
        $this->audit->record('plan.activated', subject: $plan, metadata: ['plan' => $plan->key]);

        return new PlanResource($this->fresh($plan));
    }

    /** @param  array<string, mixed>  $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'] ?? null,
            'quotas' => [
                'whatsapp' => $data['quotas']['whatsapp'] ?? null,
                'sms' => $data['quotas']['sms'] ?? null,
                'email' => $data['quotas']['email'] ?? null,
            ],
            'modules' => array_values($data['modules']),
            'is_public' => $data['is_public'],
        ];
    }

    /** Clave estable derivada del nombre: "Pyme Plus" → "pyme-plus" (o "pyme-plus-2"). */
    private function uniqueKey(string $name): string
    {
        $base = Str::limit(Str::slug($name), 34, '') ?: 'plan';
        $key = $base;
        $suffix = 2;

        while (Plan::query()->where('key', $key)->exists()) {
            $key = "{$base}-{$suffix}";
            $suffix++;
        }

        return $key;
    }

    /** Clientes que tienen el plan hoy (sin la plataforma). */
    private function withClientCounts($query)
    {
        return $query->addSelect(['plans.*', 'clients_count' => DB::table('tenants')
            ->selectRaw('count(*)')
            ->whereColumn('tenants.plan', 'plans.key')
            ->where('tenants.is_platform', false)]);
    }

    private function fresh(Plan $plan): Plan
    {
        return $this->withClientCounts(Plan::query())->findOrFail($plan->id);
    }
}
