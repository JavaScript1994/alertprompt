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
        Schema::table('plans', function (Blueprint $table) {
            // Inactivo = no se ofrece en altas nuevas; quien lo tiene lo conserva.
            $table->boolean('is_active')->default(true)->after('is_public');
            // Módulos con los que nace un cliente de este plan (antes en config/modules.php).
            $table->jsonb('modules')->default('[]')->after('quotas');
        });

        $defaults = [
            'starter' => ['sms', 'email', 'csv_import'],
            'growth' => ['whatsapp', 'sms', 'email', 'csv_import', 'scheduling', 'reports'],
            'scale' => ['whatsapp', 'sms', 'email', 'csv_import', 'scheduling', 'reports'],
            'enterprise' => ['whatsapp', 'sms', 'email', 'csv_import', 'scheduling', 'reports'],
        ];

        foreach ($defaults as $key => $modules) {
            DB::table('plans')->where('key', $key)->update(['modules' => json_encode($modules)]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'modules']);
        });
    }
};
