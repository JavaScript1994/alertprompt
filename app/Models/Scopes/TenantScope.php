<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app('current_tenant_id');

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}
