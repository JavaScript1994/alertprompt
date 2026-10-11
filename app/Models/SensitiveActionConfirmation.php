<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SensitiveAction;
use Illuminate\Database\Eloquent\Model;

class SensitiveActionConfirmation extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'confirmed_at', 'expires_at', 'ip'];

    protected function casts(): array
    {
        return [
            'action' => SensitiveAction::class,
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
