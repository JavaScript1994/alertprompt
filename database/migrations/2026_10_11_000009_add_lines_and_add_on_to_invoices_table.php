<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Detalle del comprobante: [{description, amount}] sin IGV. Null en
            // los emitidos antes de los servicios adicionales (una sola línea).
            $table->jsonb('lines')->nullable()->after('description');
            // Prorrateo de un servicio contratado a mitad de una membresía anual
            // (uno por contrato).
            $table->foreignId('tenant_add_on_id')->nullable()->unique()->after('membership_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_add_on_id');
            $table->dropColumn('lines');
        });
    }
};
