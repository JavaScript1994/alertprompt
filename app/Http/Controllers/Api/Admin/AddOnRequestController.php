<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AddOnRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AddOns\ApproveAddOnRequest;
use App\Http\Requests\Api\Admin\AddOns\RejectAddOnRequest;
use App\Http\Resources\AddOnRequestResource;
use App\Models\AddOnRequest;
use App\Models\Scopes\TenantScope;
use App\Services\AddOns\AddOnRequests;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Solicitudes de servicios de todos los clientes (sin TenantScope a propósito). */
class AddOnRequestController extends Controller
{
    public function __construct(private readonly AddOnRequests $requests) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(AddOnRequestStatus::class)], 'client_id' => ['nullable', 'integer']]);

        return AddOnRequestResource::collection($this->query()
            ->with(['tenant', 'addOn', 'requester', 'decider'])
            ->where('status', AddOnRequestStatus::from($request->input('status', 'pending')))
            ->when($request->filled('client_id'), fn (Builder $q) => $q->where('tenant_id', $request->integer('client_id')))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 100)));
    }

    public function approve(ApproveAddOnRequest $request, int $addOnRequest): AddOnRequestResource
    {
        $decided = $this->requests->approve($this->query()->findOrFail($addOnRequest), $request->user(), $request->validated('note'));

        return new AddOnRequestResource($decided->load(['tenant', 'addOn', 'requester', 'decider']));
    }

    public function reject(RejectAddOnRequest $request, int $addOnRequest): AddOnRequestResource
    {
        $decided = $this->requests->reject($this->query()->findOrFail($addOnRequest), $request->user(), $request->validated('note'));

        return new AddOnRequestResource($decided->load(['tenant', 'addOn', 'requester', 'decider']));
    }

    /** @return Builder<AddOnRequest> */
    private function query(): Builder
    {
        return AddOnRequest::query()->withoutGlobalScope(TenantScope::class);
    }
}
