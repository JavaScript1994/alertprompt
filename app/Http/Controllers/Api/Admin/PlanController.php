<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Plans\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Catálogo de planes. Los planes son fijos (TenantPlan); se editan sus condiciones. */
class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::query()->orderBy('sort')->get());
    }

    public function update(UpdatePlanRequest $request, Plan $plan, AuditLogger $audit): PlanResource
    {
        $data = $request->validated();
        $plan->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'] ?? null,
            'quotas' => [
                'whatsapp' => $data['quotas']['whatsapp'] ?? null,
                'sms' => $data['quotas']['sms'] ?? null,
                'email' => $data['quotas']['email'] ?? null,
            ],
            'is_public' => $data['is_public'],
        ]);

        $changes = array_keys($plan->getDirty());
        $plan->save();

        if ($changes !== []) {
            $audit->record('plan.updated', subject: $plan, metadata: ['plan' => $plan->key->value, 'fields' => $changes]);
        }

        return new PlanResource($plan);
    }
}
