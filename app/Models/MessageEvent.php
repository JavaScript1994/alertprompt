<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageEvent extends Model
{
    protected $fillable = [
        'campaign_recipient_id',
        'event',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class, 'campaign_recipient_id');
    }
}
