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
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'module']);
        });

        // Los clientes que ya existían conservan todo lo que podían usar.
        $now = now();
        $rows = [];
        foreach (DB::table('tenants')->where('is_platform', false)->pluck('id') as $tenantId) {
            foreach (array_keys(config('modules.catalog')) as $module) {
                $rows[] = ['tenant_id' => $tenantId, 'module' => $module, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($rows !== []) {
            DB::table('tenant_modules')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};
