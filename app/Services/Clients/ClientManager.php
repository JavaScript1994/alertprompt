<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SetPasswordLink;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Alta y ciclo de vida de clientes (empresas y personas naturales) desde el
 * panel de la plataforma.
 */
class ClientManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Crea el tenant y su administrador inicial, y le envía la invitación para
     * definir su contraseña. Nadie (ni el dueño) conoce esa contraseña.
     *
     * @param  array{type: string, name: string, document_type: string, document_number: string, contact_email?: ?string, contact_phone?: ?string, address?: ?string, plan?: string, admin_name: string, admin_email: string}  $data
     */
    public function create(array $data): Tenant
    {
        [$tenant, $admin] = DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'type' => TenantType::from($data['type']),
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'plan' => TenantPlan::from($data['plan'] ?? TenantPlan::Starter->value),
                'status' => TenantStatus::Active,
                'settings' => ['timezone' => 'America/Lima'],
            ]);

            $admin = $this->createUser($tenant, $data['admin_name'], $data['admin_email'], 'client-admin');

            $this->audit->record('client.created', $tenant->id, $tenant, [
                'admin_email' => $admin->email,
            ]);

            return [$tenant, $admin];
        });

        $this->invite($admin, $tenant);

        return $tenant;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->fill($data);
        $changes = array_keys($tenant->getDirty());
        $tenant->save();

        if ($changes !== []) {
            $this->audit->record('client.updated', $tenant->id, $tenant, ['fields' => $changes]);
        }

        return $tenant;
    }

    public function setStatus(Tenant $tenant, TenantStatus $status, ?string $reason = null): Tenant
    {
        $previous = $tenant->status;
        $tenant->update(['status' => $status]);

        $this->audit->record($status === TenantStatus::Suspended ? 'client.suspended' : 'client.reactivated', $tenant->id, $tenant, [
            'from' => $previous->value,
            'to' => $status->value,
            'reason' => $reason,
        ]);

        return $tenant;
    }

    /**
     * Usuario con contraseña aleatoria imposible de adivinar: el acceso real
     * llega por el enlace de invitación.
     */
    public function createUser(Tenant $tenant, string $name, string $email, string $role): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => Str::random(64),
        ]);

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);
        setPermissionsTeamId($previousTeam);

        return $user;
    }

    public function invite(User $user, Tenant $tenant): void
    {
        $token = Password::broker('invitations')->createToken($user);

        $user->notify(new SetPasswordLink($token, invitation: true, tenantName: $tenant->name));
    }
}
