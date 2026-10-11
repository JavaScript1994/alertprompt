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
        // Servicios adicionales: lo que un cliente contrata aparte de su plan.
        // Uno "disponible" prende un módulo de config/modules.php; uno
        // "próximamente" solo se muestra y junta interesados.
        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 60);
            $table->string('description')->nullable();
            $table->string('status', 20)->default('available'); // AddOnStatus
            $table->string('module', 40)->nullable()->unique();
            // Precio mensual sin IGV; se copia al contrato.
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Contratos: un servicio activo por cliente a la vez (índice parcial abajo).
        Schema::create('tenant_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active'); // TenantAddOnStatus
            $table->decimal('monthly_price', 10, 2);
            $table->date('starts_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX tenant_add_ons_one_active ON tenant_add_ons (tenant_id, add_on_id) WHERE status = 'active'");

        // Solicitudes del cliente; las decide la plataforma (como plan_change_requests).
        Schema::create('add_on_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending'); // AddOnRequestStatus
            $table->string('comment', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('tenant_add_on_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        // "Me interesa" en servicios próximos: uno por cliente y servicio.
        Schema::create('add_on_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['add_on_id', 'tenant_id']);
        });

        // Valores de EJEMPLO: el dueño los ajusta en Administración.
        $now = now();
        $rows = [
            ['key' => 'scheduling', 'name' => 'Envíos programados', 'description' => 'Programa tus campañas para la fecha y hora que elijas.', 'status' => 'available', 'module' => 'scheduling', 'monthly_price' => 50],
            ['key' => 'reports', 'name' => 'Reportes', 'description' => 'Métricas de entrega por campaña y exportación.', 'status' => 'available', 'module' => 'reports', 'monthly_price' => 80],
            ['key' => 'branding', 'name' => 'Marca en el login', 'description' => 'Tu logo, color y título en el acceso de tu equipo.', 'status' => 'available', 'module' => 'branding', 'monthly_price' => 60],
            ['key' => 'ai-assistant', 'name' => 'Asistente con IA', 'description' => 'Redacta y mejora tus mensajes con inteligencia artificial.', 'status' => 'coming_soon', 'module' => null, 'monthly_price' => null],
            ['key' => 'whatsapp-live-chat', 'name' => 'Chat en vivo por WhatsApp', 'description' => 'Atiende en tiempo real las respuestas de tus clientes.', 'status' => 'coming_soon', 'module' => null, 'monthly_price' => null],
            ['key' => 'attendance', 'name' => 'Control de asistencias', 'description' => 'Registra la asistencia de tu personal.', 'status' => 'coming_soon', 'module' => null, 'monthly_price' => null],
        ];

        DB::table('add_ons')->insert(array_map(fn (array $row, int $i) => [
            ...$row, 'is_active' => true, 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
        ], $rows, array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('add_on_interests');
        Schema::dropIfExists('add_on_requests');
        Schema::dropIfExists('tenant_add_ons');
        Schema::dropIfExists('add_ons');
    }
};
