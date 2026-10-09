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
    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
            'is_system' => 'boolean',
        ];
    }
}
