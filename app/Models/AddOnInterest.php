<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Me interesa" de un cliente en un servicio que aún no existe. */
class AddOnInterest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'add_on_id', 'user_id'];

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
