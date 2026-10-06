<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'plan', 'settings'];

    protected $casts = [
        'plan' => TenantPlan::class,
        'settings' => 'array',
    ];

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
