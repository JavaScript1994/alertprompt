<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'document_type', 'document_number', 'contact_email', 'contact_phone', 'address',
        'plan', 'status', 'settings',
    ];

    protected $casts = [
        'type' => TenantType::class,
        'document_type' => DocumentType::class,
        'status' => TenantStatus::class,
        'is_platform' => 'boolean',
        'settings' => 'array',
    ];

    /** El tenant de AlertPrompt. `is_platform` no es fillable: se fija solo en el seeder. */
    public static function platform(): ?self
    {
        return static::query()->where('is_platform', true)->first();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function suppressions(): HasMany
    {
        return $this->hasMany(Suppression::class);
    }
}
