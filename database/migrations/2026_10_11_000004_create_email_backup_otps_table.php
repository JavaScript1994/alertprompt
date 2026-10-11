<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_backup_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // setup: verificar un correo de respaldo nuevo. login: entrar sin el dispositivo.
            $table->string('purpose', 10);
            // Destino del código. En `setup` es el correo aún no verificado:
            // el respaldo vigente no se reemplaza hasta confirmar el nuevo.
            $table->string('email');
            // HMAC-SHA256 del código con la APP_KEY; se compara con hash_equals().
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('requested_at');
            $table->ipAddress('ip')->nullable();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_backup_otps');
    }
};
