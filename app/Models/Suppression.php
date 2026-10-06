<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\SuppressionReason;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'channel',
        'identifier',
        'reason',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'reason' => SuppressionReason::class,
    ];
}
