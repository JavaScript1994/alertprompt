<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'channel',
        'name',
        'status',
        'batch_id',
        'scheduled_at',
        'stats',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'status' => CampaignStatus::class,
        'scheduled_at' => 'datetime',
        'stats' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function transitionTo(CampaignStatus $status): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->update(['status' => $status]);

        return true;
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Running);
    }
}
