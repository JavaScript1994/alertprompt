<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailOtpPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código de 6 dígitos enviado al correo de respaldo. Solo se guarda su HMAC. */
class EmailBackupOtp extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'purpose', 'email', 'code_hash', 'attempts', 'expires_at', 'used_at', 'requested_at', 'ip'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'purpose' => EmailOtpPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'requested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
