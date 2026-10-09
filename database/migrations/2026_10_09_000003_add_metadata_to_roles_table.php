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
        Schema::table('roles', function (Blueprint $table) {
            // `name` es la clave estable que usa el código; `label` es lo que
            // ve y edita el dueño de la plataforma.
            $table->string('label')->after('name');
            $table->string('description')->nullable()->after('label');
            $table->string('scope')->default('client')->after('description');
            $table->boolean('is_system')->default(false)->after('scope');
        });

        // El unique de Spatie (tenant_id, name, guard_name) no impide duplicar
        // roles globales: en Postgres dos NULL en tenant_id cuentan como
        // distintos. COALESCE los iguala (y funciona también en SQLite de tests).
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name', 'guard_name']);
        });
        DB::statement('CREATE UNIQUE INDEX roles_tenant_name_guard_unique ON roles (COALESCE(tenant_id, 0), name, guard_name)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS roles_tenant_name_guard_unique');

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'description', 'scope', 'is_system']);
        });
    }
};
