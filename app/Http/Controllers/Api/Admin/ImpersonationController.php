<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Impersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private readonly Impersonation $impersonation) {}

    public function store(Request $request, Tenant $client): JsonResponse
    {
        $this->impersonation->start($request, $client);

        return response()->json(status: 204);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->impersonation->stop($request);

        return response()->json(status: 204);
    }
}
