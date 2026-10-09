<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 20);
            $table->string('status', 20);
            $table->string('billing_cycle', 20);
            // Precio del ciclo sin IGV.
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('PEN');
            $table->date('starts_at');
            $table->date('ends_at');
            // Cuota mensual por canal; null = sin límite.
            $table->jsonb('quotas')->default('{}');
            $table->string('contract_reference', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
