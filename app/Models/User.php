<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Notifications\SetPasswordLink;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deactivated_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            // Lista de hashes (Hash::make) de los códigos, cifrada.
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_email_verified_at' => 'datetime',
            'two_factor_grace_ends_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasMfaEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function hasVerifiedEmailBackup(): bool
    {
        return $this->two_factor_email_verified_at !== null && $this->two_factor_email_backup !== null;
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /** Reemplaza el correo en inglés de Laravel por el enlace a la SPA. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SetPasswordLink($token));
    }
}
