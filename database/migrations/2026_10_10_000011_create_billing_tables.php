<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Correlativo por serie, con bloqueo de fila al emitir.
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('series', 4)->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            // Inicio del período facturado de la membresía (evita duplicar).
            $table->date('period_start')->nullable();
            $table->string('document_type', 10);
            $table->string('series', 4);
            $table->unsignedInteger('number');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('currency', 3)->default('PEN');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('igv', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('description');
            // Datos del cliente al momento de emitir (no cambian si luego edita su perfil).
            $table->jsonb('customer');
            $table->string('status', 20)->default('issued');
            $table->string('sunat_status', 20)->default('not_sent');
            $table->string('provider_reference')->nullable();
            $table->string('pdf_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series', 'number']);
            $table->unique(['membership_id', 'period_start']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('provider', 20)->default('manual');
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_at']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_customer_id')->nullable();
            // Token de la pasarela (cifrado). Nunca el número de tarjeta.
            $table->text('provider_token');
            $table->string('brand', 20)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
