<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Antes de las invitaciones, los usuarios se creaban con contraseña conocida
 * y nunca pasaban por "crear mi contraseña". Se marcan como verificados para
 * que no figuren como "invitación pendiente".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Irreversible a propósito: no se sabe cuáles estaban vacíos.
    }
};
