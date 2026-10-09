<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Enums\Channel;
use App\Enums\TenantPlan;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Qué módulos tiene activos cada tenant. La plataforma los tiene todos.
 * Se cachea por request: lo consultan middleware, validaciones y /api/user.
 */
class TenantModules
{
    /** @var array<int, list<string>> */
    private array $cache = [];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, array{label: string, description: string}> */
    public function catalog(): array
    {
        return config('modules.catalog', []);
    }

    /** @return list<string> */
    public function enabledFor(Tenant|int $tenant): array
    {
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::query()->findOrFail($tenant);

        if ($tenant->is_platform) {
            return array_keys($this->catalog());
        }

        return $this->cache[$tenant->id] ??= DB::table('tenant_modules')
            ->where('tenant_id', $tenant->id)
            ->whereIn('module', array_keys($this->catalog()))
            ->orderBy('module')
            ->pluck('module')
            ->all();
    }

    public function isEnabled(string $module, Tenant|int|null $tenant = null): bool
    {
        $tenant ??= TenantContext::id();

        return $tenant !== null && in_array($module, $this->enabledFor($tenant), true);
    }

    public function channelEnabled(Channel $channel, Tenant|int|null $tenant = null): bool
    {
        return $this->isEnabled($channel->value, $tenant);
    }

    /** @return list<string> */
    public function defaultsFor(TenantPlan $plan): array
    {
        return config("modules.plans.{$plan->value}", []);
    }

    /**
     * Deja exactamente $modules activos y registra qué cambió.
     *
     * @param  list<string>  $modules
     */
    public function sync(Tenant $tenant, array $modules): void
    {
        $unknown = array_diff($modules, array_keys($this->catalog()));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Módulos inexistentes: '.implode(', ', $unknown));
        }

        $current = $this->enabledFor($tenant);
        $added = array_values(array_diff($modules, $current));
        $removed = array_values(array_diff($current, $modules));

        if ($added === [] && $removed === []) {
            return;
        }

        DB::transaction(function () use ($tenant, $added, $removed) {
            DB::table('tenant_modules')->where('tenant_id', $tenant->id)->whereIn('module', $removed)->delete();

            $now = now();
            DB::table('tenant_modules')->insert(array_map(fn (string $module) => [
                'tenant_id' => $tenant->id,
                'module' => $module,
                'enabled_by' => Auth::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ], $added));

            $this->audit->record('client.modules_changed', $tenant->id, $tenant, ['enabled' => $added, 'disabled' => $removed]);
        });

        unset($this->cache[$tenant->id]);
    }
}
