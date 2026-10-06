<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'phone',
        'email',
        'name',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function activeConsents(): HasMany
    {
        return $this->hasMany(Consent::class)->whereNull('revoked_at');
    }

    public function hasConsentFor(string $channel): bool
    {
        return $this->activeConsents()->where('channel', $channel)->exists();
    }
}
