<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['user_id', 'occurred_at']);
            $table->index(['tenant_id', 'event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
