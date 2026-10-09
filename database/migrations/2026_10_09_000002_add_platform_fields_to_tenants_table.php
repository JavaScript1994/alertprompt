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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('type')->default('company')->after('name');
            $table->string('status')->default('active')->after('plan');
            $table->boolean('is_platform')->default(false)->after('status');
        });

        // Un solo tenant puede ser la plataforma (AlertPrompt).
        DB::statement('CREATE UNIQUE INDEX tenants_single_platform ON tenants (is_platform) WHERE is_platform');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tenants_single_platform');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['type', 'status', 'is_platform']);
        });
    }
};
