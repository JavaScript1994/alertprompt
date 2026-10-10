<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanChangeStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido de un cliente para cambiar de plan; lo decide la plataforma. */
class PlanChangeRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'current_plan', 'requested_plan', 'status', 'comment', 'requested_by',
        'decided_by', 'decided_at', 'decision_note', 'effective_from', 'membership_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlanChangeStatus::class,
            'decided_at' => 'datetime',
            'effective_from' => 'date',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withoutGlobalScopes();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withoutGlobalScopes();
    }
}
