<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantAddOnStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Servicio adicional contratado por un cliente. Un activo por servicio a la vez. */
class TenantAddOn extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'add_on_id', 'status', 'monthly_price', 'starts_at',
        'cancelled_at', 'cancel_reason', 'activated_by', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantAddOnStatus::class,
            'monthly_price' => 'decimal:2',
            'starts_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by')->withoutGlobalScopes();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withoutGlobalScopes();
    }
}
