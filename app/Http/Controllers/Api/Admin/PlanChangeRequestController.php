<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PlanChangeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanChangeRequestResource;
use App\Models\PlanChangeRequest;
use App\Models\Scopes\TenantScope;
use App\Services\Memberships\PlanChangeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Solicitudes de cambio de plan de todos los clientes (sin TenantScope a propósito). */
class PlanChangeRequestController extends Controller
{
    public function __construct(private readonly PlanChangeManager $changes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:pending,approved,rejected,cancelled'], 'client_id' => ['nullable', 'integer']]);

        return PlanChangeRequestResource::collection($this->query()
            ->with(['tenant', 'requester', 'decider'])
            ->where('status', PlanChangeStatus::from($request->input('status', 'pending')))
            ->when($request->filled('client_id'), fn (Builder $q) => $q->where('tenant_id', $request->integer('client_id')))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 100)));
    }

    public function approve(Request $request, int $planChange): PlanChangeRequestResource
    {
        $data = $request->validate([
            'when' => ['required', 'in:now,next_period'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $change = $this->changes->approve($this->query()->findOrFail($planChange), $request->user(), $data['when'], $data['note'] ?? null);

        return new PlanChangeRequestResource($change->load(['tenant', 'requester', 'decider']));
    }

    public function reject(Request $request, int $planChange): PlanChangeRequestResource
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']], [], ['note' => 'motivo']);

        $change = $this->changes->reject($this->query()->findOrFail($planChange), $request->user(), $data['note']);

        return new PlanChangeRequestResource($change->load(['tenant', 'requester', 'decider']));
    }

    /** @return Builder<PlanChangeRequest> */
    private function query(): Builder
    {
        return PlanChangeRequest::query()->withoutGlobalScope(TenantScope::class);
    }
}
