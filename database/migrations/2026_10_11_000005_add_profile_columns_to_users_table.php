<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // `name` sigue siendo el nombre visible: el modelo lo arma con nombres + apellidos.
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('job_title', 100)->nullable();
            // Opcional (Ley 29733: solo lo necesario).
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            // Disco privado (storage/app/private); se sirve por la API con sesión.
            $table->string('photo_path')->nullable();
            // Cambio de correo pendiente de confirmar desde el correo nuevo.
            $table->string('pending_email')->nullable();
        });

        // Usuarios existentes: el nombre completo queda como "nombres" hasta que lo editen.
        DB::table('users')->whereNull('first_name')->update(['first_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'job_title', 'birth_date', 'phone', 'mobile', 'photo_path', 'pending_email']);
        });
    }
};
