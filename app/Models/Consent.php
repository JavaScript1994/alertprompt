<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'channel',
        'granted_at',
        'revoked_at',
        'source',
        'ip',
        'evidence_text',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }
}
