<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Modules\TenantModules;
use App\Support\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'login_url' => TenantDomain::urlFor($this->resource),
            'type' => $this->type,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'plan' => $this->plan,
            'plan_name' => Plan::query()->where('key', $this->plan)->value('name') ?? $this->plan,
            'status' => $this->status,
            'is_platform' => $this->is_platform,
            'timezone' => $this->settings['timezone'] ?? 'America/Lima',
            'modules' => app(TenantModules::class)->enabledFor($this->resource),
        ];
    }
}
