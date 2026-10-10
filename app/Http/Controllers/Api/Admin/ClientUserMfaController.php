<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mfa\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La administración general restablece el MFA de un usuario de un cliente
 * (típicamente su administrador, que no tiene a quién más pedírselo).
 */
class ClientUserMfaController extends Controller
{
    public function reset(Request $request, Tenant $client, int $user, MfaService $mfa): JsonResponse
    {
        abort_if($client->is_platform, 404);

        /** @var User $target */
        $target = User::query()->forTenant($client->id)->findOrFail($user);

        $mfa->resetFor($target, $request->user());

        return response()->json(status: 204);
    }
}
