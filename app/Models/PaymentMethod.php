<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Tarjeta tokenizada por la pasarela. Nunca guarda el número completo. */
class PaymentMethod extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'provider', 'provider_customer_id', 'provider_token', 'brand', 'last4', 'exp_month', 'exp_year', 'is_default', 'created_by',
    ];

    protected $hidden = ['provider_token', 'provider_customer_id'];

    protected function casts(): array
    {
        return [
            'provider_token' => 'encrypted',
            'is_default' => 'boolean',
        ];
    }
}
