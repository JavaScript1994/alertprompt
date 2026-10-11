<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddOns\RequestAddOnRequest;
use App\Http\Resources\ClientAddOnResource;
use App\Models\AddOn;
use App\Models\Tenant;
use App\Services\AddOns\AddOnRequests;
use App\Services\AddOns\ClientAddOnCatalog;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Configuración → Servicios: catálogo del cliente, solicitudes y "Me interesa". */
class AddOnController extends Controller
{
    public function __construct(private readonly AddOnRequests $requests) {}

    public function index(ClientAddOnCatalog $catalog): AnonymousResourceCollection
    {
        return ClientAddOnResource::collection($catalog->for($this->tenant()));
    }

    public function request(RequestAddOnRequest $request, AddOn $addOn): JsonResponse
    {
        $this->requests->request($this->tenant(), $request->user(), $addOn, $request->validated('comment'));

        return response()->json(status: 201);
    }

    public function cancelRequest(AddOn $addOn): JsonResponse
    {
        $pending = $this->requests->pendingFor(TenantContext::id(), $addOn) ?? abort(404, 'No tienes una solicitud pendiente de este servicio.');
        $this->requests->cancel($pending);

        return response()->json(status: 204);
    }

    public function interest(Request $request, AddOn $addOn): JsonResponse
    {
        $this->requests->markInterest($this->tenant(), $request->user(), $addOn);

        return response()->json(status: 204);
    }

    public function removeInterest(AddOn $addOn): JsonResponse
    {
        $this->requests->removeInterest($this->tenant(), $addOn);

        return response()->json(status: 204);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(TenantContext::id());
    }
}
