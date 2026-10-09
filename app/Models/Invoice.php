<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\SunatStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property InvoiceStatus $status
 * @property SunatStatus $sunat_status
 */
class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'membership_id', 'period_start', 'document_type', 'series', 'number', 'issue_date', 'due_date',
        'currency', 'subtotal', 'igv', 'total', 'description', 'customer', 'status', 'sunat_status',
        'provider_reference', 'pdf_url', 'paid_at', 'voided_at', 'void_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'customer' => 'array',
            'status' => InvoiceStatus::class,
            'sunat_status' => SunatStatus::class,
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScopes();
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class)->withoutGlobalScopes();
    }

    public function code(): string
    {
        return sprintf('%s-%08d', $this->series, $this->number);
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Issued && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function paidAmount(): string
    {
        return (string) $this->payments()->where('status', 'confirmed')->sum('amount');
    }
}
