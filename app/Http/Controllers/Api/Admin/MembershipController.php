<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Memberships\StoreMembershipRequest;
use App\Http\Resources\MembershipResource;
use App\Models\Tenant;
use App\Services\Memberships\MembershipManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipManager $memberships) {}

    public function index(Tenant $client): JsonResponse
    {
        return response()->json([
            'data' => MembershipResource::collection($this->memberships->queryFor($client->id)->with('creator')->latest('starts_at')->get()),
            'usage' => $this->memberships->usage($client->id),
        ]);
    }

    public function store(StoreMembershipRequest $request, Tenant $client): JsonResponse
    {
        $membership = $this->memberships->create($client, $request->validated());

        return (new MembershipResource($membership->load('creator')))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Tenant $client, int $membership): MembershipResource
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $model = $this->memberships->queryFor($client->id)->findOrFail($membership);

        return new MembershipResource($this->memberships->cancel($model, $request->input('reason')));
    }
}
