<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SunatStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota de crédito sobre un comprobante; lo no aplicado es saldo a favor del cliente. */
class CreditNote extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'tenant_add_on_id', 'series', 'number', 'issue_date', 'currency',
        'subtotal', 'igv', 'total', 'reason', 'lines', 'customer', 'sunat_status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'lines' => 'array',
            'customer' => 'array',
            'sunat_status' => SunatStatus::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }

    public function code(): string
    {
        return sprintf('%s-%08d', $this->series, $this->number);
    }

    public function appliedAmount(): string
    {
        return (string) $this->applications()->sum('amount');
    }

    public function remaining(): float
    {
        return max(0, round((float) $this->total - (float) $this->appliedAmount(), 2));
    }
}
