<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use App\Enums\SkipReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'status',
        'provider_message_id',
        'error_code',
        'skip_reason',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'status' => CampaignRecipientStatus::class,
        'skip_reason' => SkipReason::class,
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessageEvent::class);
    }
}
