<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Parte de una nota de crédito usada para saldar un comprobante. */
class CreditApplication extends Model
{
    protected $fillable = ['credit_note_id', 'invoice_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class)->withoutGlobalScopes();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }
}
