<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Roles;

use App\Enums\RoleScope;
use App\Services\Authorization\PermissionRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cada permiso debe existir en el árbol y corresponder al scope del rol: un
 * rol de cliente no puede llevar permisos de administración de la plataforma.
 */
class RolePermissionsRule implements ValidationRule
{
    public function __construct(private readonly ?RoleScope $scope) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $this->scope === null) {
            return;
        }

        $registry = app(PermissionRegistry::class);
        $allowed = $this->scope === RoleScope::Platform ? $registry->all() : $registry->forScope(RoleScope::Client);

        $unknown = array_diff($value, $registry->all());
        if ($unknown !== []) {
            $fail('Permisos inexistentes: '.implode(', ', $unknown).'.');

            return;
        }

        $outOfScope = array_diff($value, $allowed);
        if ($outOfScope !== []) {
            $fail('Un rol de cliente no puede incluir permisos de administración: '.implode(', ', $outOfScope).'.');
        }
    }
}
