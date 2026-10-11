<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan del catálogo comercial (global, sin tenant). `key` es la clave que
 * guardan tenants.plan y memberships.plan; no cambia al renombrar. Las
 * membresías copian precio y cuotas al crearse.
 *
 * @property string $key
 * @property array<string, int|null> $quotas
 * @property list<string> $modules
 */
class Plan extends Model
{
    protected $fillable = ['key', 'name', 'description', 'monthly_price', 'quotas', 'modules', 'is_public', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'quotas' => 'array',
            'modules' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<Plan>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function defaultKey(): string
    {
        return static::query()->active()->orderBy('sort')->value('key') ?? 'basico';
    }
}
