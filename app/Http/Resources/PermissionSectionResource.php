<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una sección del árbol de permisos (sección → módulo → permiso).
 *
 * @property array{key: string, label: string, scope: string, modules: list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>} $resource
 */
class PermissionSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'label' => $this->resource['label'],
            'scope' => $this->resource['scope'],
            'modules' => $this->resource['modules'],
        ];
    }
}
