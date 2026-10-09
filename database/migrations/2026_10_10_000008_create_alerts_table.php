<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60);
            $table->string('severity', 20);
            $table->string('title');
            $table->text('message');
            $table->jsonb('data')->default('{}');
            // Agrupa repeticiones de la misma señal mientras siga abierta.
            $table->string('fingerprint', 120);
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resolved_at', 'severity']);
            $table->index(['tenant_id', 'resolved_at']);
            $table->index(['fingerprint', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
