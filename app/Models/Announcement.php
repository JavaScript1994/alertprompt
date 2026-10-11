<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comunicado de la plataforma a sus clientes. Sin TenantScope: lo escribe la
 * plataforma y cada cliente ve solo los que le corresponden (visibleTo).
 */
class Announcement extends Model
{
    protected $fillable = [
        'type', 'title', 'body', 'link_url', 'link_label', 'audience', 'plan_keys', 'admins_only',
        'published_at', 'expires_at', 'send_email', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'audience' => AnnouncementAudience::class,
            'plan_keys' => 'array',
            'admins_only' => 'boolean',
            'send_email' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /** Publicado, vigente y dirigido a este cliente. El filtro por rol va aparte. */
    public function scopeLiveFor(Builder $query, Tenant $tenant): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q
                ->where('audience', AnnouncementAudience::All)
                ->orWhere(fn ($q) => $q->where('audience', AnnouncementAudience::Tenants)
                    ->whereHas('tenants', fn ($q) => $q->whereKey($tenant->id)))
                ->orWhere(fn ($q) => $q->where('audience', AnnouncementAudience::Plans)
                    ->whereJsonContains('plan_keys', (string) $tenant->plan)));
    }

    public function status(): string
    {
        return match (true) {
            $this->published_at === null => 'draft',
            $this->published_at->isFuture() => 'scheduled',
            $this->resolved_at !== null => 'resolved',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            default => 'live',
        };
    }
}
