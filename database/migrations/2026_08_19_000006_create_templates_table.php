<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('category');
            $table->string('name');
            $table->text('body');
            $table->jsonb('variables')->default('[]');
            $table->string('provider_template_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'channel', 'status'], 'templates_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
