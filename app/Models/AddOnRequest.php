<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AddOnRequestStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido de un cliente para contratar un servicio adicional; lo decide la plataforma. */
class AddOnRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'add_on_id', 'status', 'comment', 'requested_by',
        'decided_by', 'decided_at', 'decision_note', 'tenant_add_on_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AddOnRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
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
