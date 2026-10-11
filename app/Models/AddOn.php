<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AddOnStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Servicio adicional del catálogo (global, sin tenant). Uno disponible prende
 * `module`; uno próximo solo junta interesados. Los contratos copian el precio.
 *
 * @property string $key
 * @property AddOnStatus $status
 * @property ?string $module
 */
class AddOn extends Model
{
    protected $fillable = ['key', 'name', 'description', 'status', 'module', 'monthly_price', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'status' => AddOnStatus::class,
            'monthly_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<AddOn>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === AddOnStatus::Available && $this->module !== null;
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(TenantAddOn::class)->withoutGlobalScopes();
    }

    public function interests(): HasMany
    {
        return $this->hasMany(AddOnInterest::class)->withoutGlobalScopes();
    }
}
