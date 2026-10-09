<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MembershipResource;
use App\Services\Memberships\MembershipManager;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/** Membresía del propio cliente: vigente, próxima, consumo del mes e historial. */
class MembershipController extends Controller
{
    public function show(MembershipManager $memberships): JsonResponse
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
        ]]);
    }
}
