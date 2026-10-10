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
        // Catálogo comercial: lo que muestra la pantalla de Membresía y lo que
        // propone el alta de una membresía. Una membresía copia precio y
        // cuotas al crearse, así que editar el catálogo no cambia contratos vigentes.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique(); // = TenantPlan
            $table->string('name', 60);
            $table->string('description')->nullable();
            // Precio mensual sin IGV; null = a medida (cotización).
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->jsonb('quotas')->default('{}');
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Valores de EJEMPLO: el dueño los ajusta en Administración > Planes.
        $now = now();
        DB::table('plans')->insert([
            ['key' => 'starter', 'name' => 'Starter', 'description' => 'Para empezar a comunicarte con tus clientes.', 'monthly_price' => 150, 'quotas' => json_encode(['whatsapp' => 1000, 'sms' => 1000, 'email' => 1000]), 'is_public' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'growth', 'name' => 'Growth', 'description' => 'Campañas frecuentes y reportes.', 'monthly_price' => 450, 'quotas' => json_encode(['whatsapp' => 5000, 'sms' => 5000, 'email' => 5000]), 'is_public' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'scale', 'name' => 'Scale', 'description' => 'Alto volumen con varios equipos.', 'monthly_price' => 1200, 'quotas' => json_encode(['whatsapp' => 20000, 'sms' => 20000, 'email' => 20000]), 'is_public' => true, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'enterprise', 'name' => 'Enterprise', 'description' => 'Volumen y condiciones a medida.', 'monthly_price' => null, 'quotas' => json_encode(['whatsapp' => null, 'sms' => null, 'email' => null]), 'is_public' => false, 'sort' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
