<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleScope;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rol con metadatos para la UI. `tenant_id` null = rol global, disponible
 * para todos los tenants de su `scope`. Los roles los gestiona el dueño de
 * la plataforma; por eso este modelo NO usa el TenantScope.
 *
 * @property string $label
 * @property ?string $description
 * @property RoleScope $scope
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    public const OWNER = 'platform-owner';

    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * El rol de dueño no se edita ni se elimina: `permissions:sync` lo
     * mantiene siempre con todos los permisos.
     */
    public function isLocked(): bool
    {
        return $this->name === self::OWNER;
    }
}
