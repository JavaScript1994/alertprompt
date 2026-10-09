<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Settings\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Support\TenantContext;

class TenantSettingsController extends Controller
{
    public function show(): TenantResource
    {
        return new TenantResource($this->tenant());
    }

    public function update(UpdateTenantSettingsRequest $request, AuditLogger $audit): TenantResource
    {
        $tenant = $this->tenant();
        $data = $request->validated();

        $tenant->fill([
            'name' => $data['name'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'settings' => [...($tenant->settings ?? []), 'timezone' => $data['timezone']],
        ]);
        $changes = array_keys($tenant->getDirty());
        $tenant->save();

        if ($changes !== []) {
            $audit->record('tenant.settings_updated', $tenant->id, $tenant, ['fields' => $changes]);
        }

        return new TenantResource($tenant);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(TenantContext::id());
    }
}
