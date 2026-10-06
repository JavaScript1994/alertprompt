<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'channel',
        'category',
        'name',
        'body',
        'variables',
        'provider_template_id',
        'status',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'category' => TemplateCategory::class,
        'status' => TemplateStatus::class,
        'variables' => 'array',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function requiresConsent(): bool
    {
        return $this->category->requiresConsent();
    }
}
