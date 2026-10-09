<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Modules\UpdateClientModulesRequest;
use App\Models\Tenant;
use App\Services\Modules\TenantModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    public function __construct(private readonly TenantModules $modules) {}

    /** Catálogo con cuántos clientes usan cada módulo y en qué planes viene incluido. */
    public function index(): JsonResponse
    {
        $usage = DB::table('tenant_modules')
            ->join('tenants', 'tenants.id', '=', 'tenant_modules.tenant_id')
            ->where('tenants.is_platform', false)
            ->groupBy('module')
            ->selectRaw('module, count(*) as total')
            ->pluck('total', 'module');

        $plans = config('modules.plans', []);

        return response()->json(['data' => collect($this->modules->catalog())->map(fn (array $module, string $key) => [
            'key' => $key,
            'label' => $module['label'],
            'description' => $module['description'],
            'clients_count' => (int) ($usage[$key] ?? 0),
            'included_in_plans' => array_keys(array_filter($plans, fn (array $included) => in_array($key, $included, true))),
        ])->values()]);
    }

    public function show(Tenant $client): JsonResponse
    {
        return response()->json(['data' => $this->modules->enabledFor($client)]);
    }

    public function update(UpdateClientModulesRequest $request, Tenant $client): JsonResponse
    {
        $this->modules->sync($client, $request->input('modules'));

        return response()->json(['data' => $this->modules->enabledFor($client)]);
    }
}
