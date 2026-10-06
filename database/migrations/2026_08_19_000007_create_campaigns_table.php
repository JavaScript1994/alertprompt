<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            $table->string('channel');
            $table->string('name');
            $table->string('status')->default('draft');
            $table->uuid('batch_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->jsonb('stats')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status'], 'campaigns_status_idx');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
