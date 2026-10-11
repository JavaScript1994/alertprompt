<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Planes en español: la clave también, porque viaja por la API y aparece en
 * comprobantes y auditoría. Se actualizan las referencias de clientes y
 * membresías en la misma transacción.
 */
return new class extends Migration
{
    private const MAP = [
        'starter' => ['basico', 'Básico'],
        'growth' => ['intermedio', 'Intermedio'],
        'scale' => ['avanzado', 'Avanzado'],
        'enterprise' => ['empresarial', 'Empresarial'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::MAP as $old => [$key, $name]) {
                // El nombre solo se traduce si sigue siendo el de fábrica.
                $plan = DB::table('plans')->where('key', $old)->first();
                if ($plan !== null) {
                    DB::table('plans')->where('key', $old)->update([
                        'key' => $key,
                        'name' => strcasecmp($plan->name, $old) === 0 ? $name : $plan->name,
                    ]);
                }

                DB::table('tenants')->where('plan', $old)->update(['plan' => $key]);
                DB::table('memberships')->where('plan', $old)->update(['plan' => $key]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::MAP as $old => [$key, $name]) {
                DB::table('plans')->where('key', $key)->update(['key' => $old, 'name' => ucfirst($old)]);
                DB::table('tenants')->where('plan', $key)->update(['plan' => $old]);
                DB::table('memberships')->where('plan', $key)->update(['plan' => $old]);
            }
        });
    }
};
