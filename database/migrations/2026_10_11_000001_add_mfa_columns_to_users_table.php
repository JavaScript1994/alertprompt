<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Cifrados con la APP_KEY (casts del modelo); nunca en claro.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // Siempre en minúsculas (string en vez de CITEXT: los tests corren en SQLite).
            $table->string('two_factor_email_backup')->nullable();
            $table->timestamp('two_factor_email_verified_at')->nullable();
            // Fin del plazo para enrolar de quien no lo hace en el primer login.
            $table->timestamp('two_factor_grace_ends_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_email_backup',
                'two_factor_email_verified_at',
                'two_factor_grace_ends_at',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
