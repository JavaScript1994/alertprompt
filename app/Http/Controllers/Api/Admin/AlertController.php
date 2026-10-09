<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AlertSeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\Scopes\TenantScope;
use App\Services\Alerts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bandeja de alertas de todos los clientes. Sin TenantScope a propósito:
 * solo la alcanza la plataforma (middleware platform + admin.alerts.*).
 */
class AlertController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'in:open,resolved'],
            'severity' => ['nullable', 'in:info,warning,critical'],
            'tenant_id' => ['nullable', 'integer'],
        ]);

        $alerts = $this->query()
            ->with(['tenant', 'campaign', 'resolver'])
            ->when($request->input('status', 'open') === 'open', fn (Builder $q) => $q->whereNull('resolved_at'), fn (Builder $q) => $q->whereNotNull('resolved_at'))
            ->when($request->filled('severity'), fn (Builder $q) => $q->where('severity', AlertSeverity::from($request->string('severity')->toString())))
            ->when($request->filled('tenant_id'), fn (Builder $q) => $q->where('tenant_id', $request->integer('tenant_id')))
            // Primero lo crítico y lo más reciente.
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->latest('updated_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return AlertResource::collection($alerts);
    }

    public function resolve(Request $request, int $alert, Alerts $alerts): AlertResource
    {
        $model = $this->query()->findOrFail($alert);

        return new AlertResource($alerts->resolve($model, $request->user()->id)->load(['tenant', 'campaign', 'resolver']));
    }

    /** @return Builder<Alert> */
    private function query(): Builder
    {
        return Alert::query()->withoutGlobalScope(TenantScope::class);
    }
}
