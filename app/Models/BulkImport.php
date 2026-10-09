<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carga masiva hecha por la plataforma a nombre de un cliente. Sin
 * TenantScope: la crea y la lee la plataforma (rutas /api/admin/*).
 */
class BulkImport extends Model
{
    /** Declaración que acepta quien sube la base; se guarda tal cual. */
    public const ATTESTATION = 'Declaro que cada contacto con consentimiento en este archivo lo otorgó de forma previa, libre, informada, expresa e inequívoca, por iniciativa propia, y que la evidencia indicada es verídica. Esta base no fue comprada ni obtenida de terceros sin consentimiento (Ley N° 32323).';

    protected $fillable = [
        'tenant_id', 'uploaded_by', 'original_filename', 'path', 'declared_source', 'attestation_text',
        'status', 'rows', 'created', 'updated', 'consents_recorded', 'without_consent', 'consents_blocked',
        'invalid', 'errors', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withoutGlobalScopes();
    }
}
