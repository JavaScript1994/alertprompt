<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AddOns\CancelContractRequest;
use App\Http\Requests\Api\Admin\AddOns\ContractAddOnRequest;
use App\Http\Resources\TenantAddOnResource;
use App\Models\AddOn;
use App\Models\Tenant;
use App\Models\TenantAddOn;
use App\Services\AddOns\AddOnManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Servicios contratados de un cliente: historial, alta directa y cancelación. */
class ClientAddOnController extends Controller
{
    public function __construct(private readonly AddOnManager $contracts) {}

    public function index(Tenant $client): AnonymousResourceCollection
    {
        return TenantAddOnResource::collection(TenantAddOn::query()->forTenant($client->id)
            ->with(['addOn', 'activator', 'canceller'])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->latest('id')
            ->get());
    }

    public function store(ContractAddOnRequest $request, Tenant $client): JsonResponse
    {
        $contract = $this->contracts->contract($client, AddOn::query()->findOrFail($request->validated('add_on_id')), $request->user());

        return (new TenantAddOnResource($contract->load(['addOn', 'activator'])))->response()->setStatusCode(201);
    }

    public function cancel(CancelContractRequest $request, Tenant $client, int $contract): TenantAddOnResource
    {
        $model = TenantAddOn::query()->forTenant($client->id)->findOrFail($contract);

        return new TenantAddOnResource($this->contracts->cancel($model, $request->user(), $request->validated('reason'))->load(['addOn', 'activator', 'canceller']));
    }
}
