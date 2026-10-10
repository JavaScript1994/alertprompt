<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regla nueva: todo cliente tiene membresía. Los que no tienen una vigente
 * ni programada reciben la de su plan con las condiciones del catálogo, por
 * 12 meses desde hoy. Los comprobantes los emite billing:daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        $today = now()->startOfDay();
        $plans = DB::table('plans')->get()->keyBy('key');
        $fallback = DB::table('plans')->where('is_active', true)->orderBy('sort')->value('key');

        $clients = DB::table('tenants')
            ->where('is_platform', false)
            ->whereNotExists(fn ($q) => $q->from('memberships')
                ->whereColumn('memberships.tenant_id', 'tenants.id')
                ->whereIn('memberships.status', ['active', 'scheduled']))
            ->get(['id', 'plan']);

        foreach ($clients as $client) {
            $plan = $plans[$client->plan] ?? $plans[$fallback] ?? null;
            if ($plan === null) {
                continue;
            }

            DB::table('memberships')->insert([
                'tenant_id' => $client->id,
                'plan' => $plan->key,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'price' => $plan->monthly_price ?? 0,
                'currency' => 'PEN',
                'starts_at' => $today->toDateString(),
                'ends_at' => $today->copy()->addYear()->subDay()->toDateString(),
                'quotas' => $plan->quotas,
                'notes' => 'Asignada al exigir membresía para todo cliente.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('tenants')->where('id', $client->id)->update(['plan' => $plan->key]);
        }
    }

    public function down(): void
    {
        DB::table('memberships')->where('notes', 'Asignada al exigir membresía para todo cliente.')->delete();
    }
};
