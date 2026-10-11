<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('title', 120);
            $table->text('body');
            // Ruta interna (/settings/membership) o https://...
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 40)->nullable();
            $table->string('audience', 10)->default('all');
            $table->jsonb('plan_keys')->nullable();
            // true = solo quienes gestionan usuarios en su empresa.
            $table->boolean('admins_only')->default(false);
            // null = borrador. En el futuro = programado.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('send_email')->default(false);
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['published_at', 'expires_at']);
        });

        Schema::create('announcement_tenant', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->primary(['announcement_id', 'tenant_id']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            // Banner de "acción requerida" cerrado por el usuario.
            $table->timestamp('dismissed_at')->nullable();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcement_tenant');
        Schema::dropIfExists('announcements');
    }
};
