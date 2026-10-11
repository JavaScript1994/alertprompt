<?php

declare(strict_types=1);

use App\Support\TenantSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Subdominio del login de la empresa. La plataforma no tiene (usa el dominio base).
            $table->string('slug', 40)->nullable()->unique();
            // Disco privado; se sirve por /api/branding/logo (público, es la cara del login).
            $table->string('brand_logo_path')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('brand_title', 80)->nullable();
        });

        // Identificador para los clientes existentes, a partir de su nombre.
        DB::table('tenants')->where('is_platform', false)->orderBy('id')->get(['id', 'name'])
            ->each(fn ($tenant) => DB::table('tenants')->where('id', $tenant->id)->update([
                'slug' => TenantSlug::unique((string) $tenant->name),
            ]));

        // Módulo nuevo en los planes Avanzado y Empresarial, y en sus clientes actuales.
        foreach (DB::table('plans')->whereIn('key', ['avanzado', 'empresarial'])->get(['key', 'modules']) as $plan) {
            $modules = json_decode((string) $plan->modules, true) ?: [];
            if (! in_array('branding', $modules, true)) {
                $modules[] = 'branding';
                DB::table('plans')->where('key', $plan->key)->update(['modules' => json_encode($modules)]);
            }

            DB::table('tenants')->where('plan', $plan->key)->where('is_platform', false)->pluck('id')
                ->each(fn ($tenantId) => DB::table('tenant_modules')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'module' => 'branding',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
        }
    }

    public function down(): void
    {
        DB::table('tenant_modules')->where('module', 'branding')->delete();

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'brand_logo_path', 'brand_color', 'brand_title']);
        });
    }
};
