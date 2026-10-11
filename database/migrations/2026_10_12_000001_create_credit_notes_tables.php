<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notas de crédito: devuelven lo cobrado y no usado (p. ej. un servicio
        // anual cancelado). No mueven dinero: se aplican al saldo del
        // comprobante que corrigen y lo que sobra queda como saldo a favor.
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Comprobante que corrige (SUNAT exige la referencia).
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_add_on_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('series', 4);
            $table->unsignedInteger('number');
            $table->date('issue_date');
            $table->string('currency', 3)->default('PEN');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('igv', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('reason');
            $table->jsonb('lines');
            $table->jsonb('customer');
            $table->string('sunat_status', 20)->default('not_sent');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series', 'number']);
            $table->index(['tenant_id', 'issue_date']);
        });

        // Cuánto de cada nota se usó para saldar qué comprobante.
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('credit_notes');
    }
};
