<?php

declare(strict_types=1);

namespace App\Services\AddOns;

use App\Enums\AddOnRequestStatus;
use App\Enums\AddOnStatus;
use App\Enums\AlertSeverity;
use App\Models\AddOn;
use App\Models\AddOnInterest;
use App\Models\AddOnRequest;
use App\Models\Alert;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Alerts;
use App\Services\AuditLogger;
use App\Services\Modules\TenantModules;
use Illuminate\Validation\ValidationException;

/**
 * Lo que el cliente hace en Configuración → Servicios: pedir un servicio
 * disponible (lo decide la plataforma) o marcar "Me interesa" en uno próximo.
 * A lo sumo una solicitud pendiente por servicio.
 */
class AddOnRequests
{
    public function __construct(
        private readonly TenantModules $modules,
        private readonly AuditLogger $audit,
        private readonly Alerts $alerts,
    ) {}

    public function pendingFor(int $tenantId, AddOn $addOn): ?AddOnRequest
    {
        return AddOnRequest::query()->forTenant($tenantId)
            ->where('add_on_id', $addOn->id)
            ->where('status', AddOnRequestStatus::Pending)
            ->first();
    }

    public function request(Tenant $tenant, User $user, AddOn $addOn, ?string $comment): AddOnRequest
    {
        if (! $addOn->isAvailable()) {
            throw ValidationException::withMessages(['add_on' => 'Ese servicio no está disponible.']);
        }
        if ($this->modules->isEnabled($addOn->module, $tenant)) {
            throw ValidationException::withMessages(['add_on' => "Ya tienes «{$addOn->name}» activo."]);
        }
        if ($this->pendingFor($tenant->id, $addOn) !== null) {
            throw ValidationException::withMessages(['add_on' => 'Ya solicitaste este servicio. Te avisaremos cuando AlertPrompt lo revise.']);
        }

        $request = AddOnRequest::query()->create([
            'tenant_id' => $tenant->id,
            'add_on_id' => $addOn->id,
            'status' => AddOnRequestStatus::Pending,
            'comment' => $comment,
            'requested_by' => $user->id,
        ]);

        $this->audit->record('add_on.requested', $tenant->id, $request, ['add_on' => $addOn->key]);
        $this->alerts->raise(
            tenantId: $tenant->id,
            type: 'add_on.requested',
            severity: AlertSeverity::Info,
            title: "{$tenant->name} solicita el servicio {$addOn->name}",
            message: 'Revísalo en Facturación > Solicitudes de servicios.'.($comment ? " Comentario: «{$comment}»" : ''),
            subjectKey: "add-on-request-{$request->id}",
        );

        return $request;
    }

    public function cancel(AddOnRequest $request): AddOnRequest
    {
        if ($request->status !== AddOnRequestStatus::Pending) {
            throw ValidationException::withMessages(['request' => 'La solicitud ya fue atendida.']);
        }

        $request->update(['status' => AddOnRequestStatus::Cancelled]);
        $this->audit->record('add_on.request_cancelled', $request->tenant_id, $request, ['add_on' => $request->addOn->key]);
        $this->resolveAlert($request, null);

        return $request;
    }

    /** "Me interesa": idempotente; avisa a la plataforma solo la primera vez. */
    public function markInterest(Tenant $tenant, User $user, AddOn $addOn): AddOnInterest
    {
        if (! $addOn->is_active || $addOn->status !== AddOnStatus::ComingSoon) {
            throw ValidationException::withMessages(['add_on' => 'Ese servicio no admite "Me interesa".']);
        }

        $interest = AddOnInterest::query()->forTenant($tenant->id)->firstOrCreate(
            ['tenant_id' => $tenant->id, 'add_on_id' => $addOn->id],
            ['user_id' => $user->id],
        );

        if ($interest->wasRecentlyCreated) {
            $this->audit->record('add_on.interested', $tenant->id, $interest, ['add_on' => $addOn->key]);
            $this->alerts->raise(
                tenantId: $tenant->id,
                type: 'add_on.interested',
                severity: AlertSeverity::Info,
                title: "{$tenant->name} está interesado en {$addOn->name}",
                message: 'Servicio aún no disponible. Los interesados se ven en Facturación > Servicios.',
                subjectKey: "add-on-interest-{$addOn->id}",
            );
        }

        return $interest;
    }

    public function removeInterest(Tenant $tenant, AddOn $addOn): void
    {
        $deleted = AddOnInterest::query()->forTenant($tenant->id)->where('add_on_id', $addOn->id)->delete();

        if ($deleted > 0) {
            $this->audit->record('add_on.interest_removed', $tenant->id, $addOn, ['add_on' => $addOn->key]);
        }
    }

    public function resolveAlert(AddOnRequest $request, ?int $userId): void
    {
        Alert::query()->withoutGlobalScope(TenantScope::class)
            ->where('fingerprint', "{$request->tenant_id}:add_on.requested:add-on-request-{$request->id}")
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by' => $userId]);
    }
}
