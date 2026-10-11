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
        'first_name',
        'last_name',
        'job_title',
        'birth_date',
        'phone',
        'mobile',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_pending_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deactivated_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_pending_secret' => 'encrypted',
            // Lista de hashes (Hash::make) de los códigos, cifrada.
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_email_verified_at' => 'datetime',
            'two_factor_grace_ends_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
        ];
    }

    /**
     * `name` (lo que se muestra en todo el panel) se arma con nombres +
     * apellidos. Altas antiguas que solo traen `name` lo usan como nombres.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty(['first_name', 'last_name']) && $user->first_name !== null) {
                $user->name = trim($user->first_name.' '.($user->last_name ?? ''));
            } elseif ($user->first_name === null && $user->name !== null) {
                $user->first_name = $user->name;
            }
        });
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
