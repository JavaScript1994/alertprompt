<?php

declare(strict_types=1);

namespace App\Services\AddOns;

use App\Enums\AddOnRequestStatus;
use App\Enums\AddOnStatus;
use App\Models\AddOn;
use App\Models\AddOnInterest;
use App\Models\AddOnRequest;
use App\Models\Tenant;
use App\Services\Modules\TenantModules;
use Illuminate\Database\Eloquent\Collection;

/**
 * El catálogo tal como lo ve un cliente: cada servicio con su estado para él
 * (contratado, incluido, en revisión, disponible, próximamente). Los
 * inactivos solo aparecen si los tiene contratados.
 */
class ClientAddOnCatalog
{
    public function __construct(
        private readonly AddOnManager $contracts,
        private readonly TenantModules $modules,
    ) {}

    /** @return Collection<int, AddOn> con `client_state`, `interested` y las relaciones contract / pendingRequest / lastDecision. */
    public function for(Tenant $tenant): Collection
    {
        $contracts = $this->contracts->activeFor($tenant->id)->keyBy('add_on_id');
        $enabled = $this->modules->enabledFor($tenant);
        $interested = AddOnInterest::query()->forTenant($tenant->id)->pluck('add_on_id')->all();

        $requests = AddOnRequest::query()->forTenant($tenant->id)->with('requester:id,name')->latest('id')->get();
        $pending = $requests->where('status', AddOnRequestStatus::Pending)->keyBy('add_on_id');
        $decided = $requests->whereIn('status', [AddOnRequestStatus::Approved, AddOnRequestStatus::Rejected])->unique('add_on_id')->keyBy('add_on_id');

        return AddOn::query()->orderBy('sort')->get()
            ->filter(fn (AddOn $addOn) => $addOn->is_active || $contracts->has($addOn->id))
            ->each(function (AddOn $addOn) use ($contracts, $enabled, $interested, $pending, $decided) {
                $addOn->setRelation('contract', $contracts->get($addOn->id));
                $addOn->setRelation('pendingRequest', $pending->get($addOn->id));
                $addOn->setRelation('lastDecision', $decided->get($addOn->id));
                $addOn->setAttribute('interested', in_array($addOn->id, $interested, true));
                $addOn->setAttribute('client_state', match (true) {
                    $contracts->has($addOn->id) => 'contracted',
                    $addOn->module !== null && in_array($addOn->module, $enabled, true) => 'included',
                    $addOn->status === AddOnStatus::ComingSoon => 'coming_soon',
                    $pending->has($addOn->id) => 'pending',
                    default => 'available',
                });
            })
            ->values();
    }
}
