<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MembershipStatus;
use App\Enums\PlanChangeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MembershipResource;
use App\Http\Resources\PlanChangeRequestResource;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Services\Memberships\MembershipManager;
use App\Services\Memberships\PlanChangeManager;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Membresía del propio cliente: vigente, próxima, consumo del mes e historial. */
class MembershipController extends Controller
{
    public function show(MembershipManager $memberships, PlanChangeManager $changes): JsonResponse
    {
        $tenantId = TenantContext::id();
        $all = $memberships->queryFor($tenantId)->latest('starts_at')->limit(12)->get();
        $current = $all->firstWhere('status', MembershipStatus::Active);
        $next = $all->where('status', MembershipStatus::Scheduled)->sortBy('starts_at')->first();

        return response()->json(['data' => [
            'current' => $current ? new MembershipResource($current) : null,
            'next' => $next ? new MembershipResource($next) : null,
            'usage' => $memberships->usage($tenantId),
            'quotas_enforced' => (bool) config('memberships.enforce_quotas'),
            'history' => MembershipResource::collection($all),
            // Planes para comparar; el vigente se marca aunque no sea público.
            'plans' => PlanResource::collection(Plan::query()
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('is_public', true)->where('is_active', true))
                    ->when($current, fn ($q) => $q->orWhere('key', $current->plan)))
                ->orderBy('sort')
                ->get()),
            'current_plan' => $current?->plan,
            'pending_request' => ($pending = $changes->pendingFor($tenantId)) ? new PlanChangeRequestResource($pending) : null,
            'last_decision' => ($last = PlanChangeRequest::query()
                ->whereIn('status', [PlanChangeStatus::Approved, PlanChangeStatus::Rejected])
                // Solo decisiones recientes: el aviso no queda para siempre.
                ->where('decided_at', '>=', now()->subDays(30))
                ->latest('decided_at')
                ->first()) ? new PlanChangeRequestResource($last) : null,
        ]]);
    }

    public function requestChange(Request $request, PlanChangeManager $changes): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'max:40'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $change = $changes->request(Tenant::query()->findOrFail(TenantContext::id()), $request->user(), $data['plan'], $data['comment'] ?? null);

        return (new PlanChangeRequestResource($change))->response()->setStatusCode(201);
    }

    public function cancelChange(PlanChangeManager $changes): JsonResponse
    {
        $pending = $changes->pendingFor(TenantContext::id()) ?? abort(404, 'No tienes solicitudes pendientes.');
        $changes->cancel($pending);

        return response()->json(status: 204);
    }
}
