<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\MembershipStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contrato de un cliente con AlertPrompt: plan, precio, vigencia y cuotas.
 *
 * @property MembershipStatus $status
 * @property array<string, int|null> $quotas
 */
class Membership extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'plan', 'status', 'billing_cycle', 'price', 'currency', 'starts_at', 'ends_at',
        'quotas', 'contract_reference', 'notes', 'created_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'billing_cycle' => BillingCycle::class,
            'price' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'quotas' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }
}
