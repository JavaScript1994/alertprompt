<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_imports', function (Blueprint $table) {
            $table->id();
            // Cliente al que pertenecen los contactos importados.
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('path');
            // Evidencia de la carga (Ley 32323): de dónde salió la base y la
            // declaración exacta que aceptó quien la subió.
            $table->text('declared_source');
            $table->text('attestation_text');
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('consents_recorded')->default(0);
            $table->unsignedInteger('without_consent')->default(0);
            $table->unsignedInteger('consents_blocked')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->jsonb('errors')->default('[]');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_imports');
    }
};
