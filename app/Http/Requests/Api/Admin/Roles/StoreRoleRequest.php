<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Roles;

use App\Enums\RoleScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $scope = RoleScope::tryFrom((string) $this->input('scope'));

        return [
            'label' => [
                'required', 'string', 'max:60',
                Rule::unique('roles', 'label')->where(fn ($query) => $query->whereNull('tenant_id')->where('scope', $scope?->value)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', new Enum(RoleScope::class)],
            'permissions' => ['present', 'array', new RolePermissionsRule($scope)],
            'permissions.*' => ['string', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return ['label' => 'nombre del rol', 'scope' => 'panel', 'permissions' => 'permisos'];
    }
}
