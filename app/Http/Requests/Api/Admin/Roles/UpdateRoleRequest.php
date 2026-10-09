<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Roles;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** El scope de un rol no cambia después de creado: sus usuarios dependen de él. */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'label' => [
                'required', 'string', 'max:60',
                Rule::unique('roles', 'label')
                    ->where(fn ($query) => $query->whereNull('tenant_id')->where('scope', $role->scope->value))
                    ->ignore($role->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array', new RolePermissionsRule($role->scope)],
            'permissions.*' => ['string', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return ['label' => 'nombre del rol', 'permissions' => 'permisos'];
    }
}
