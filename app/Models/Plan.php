<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantPlan;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan del catálogo comercial (global, sin tenant). Las membresías copian
 * su precio y cuotas al crearse.
 *
 * @property array<string, int|null> $quotas
 */
class Plan extends Model
{
    protected $fillable = ['name', 'description', 'monthly_price', 'quotas', 'is_public', 'sort'];

    protected function casts(): array
    {
        return [
            'key' => TenantPlan::class,
            'monthly_price' => 'decimal:2',
            'quotas' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
