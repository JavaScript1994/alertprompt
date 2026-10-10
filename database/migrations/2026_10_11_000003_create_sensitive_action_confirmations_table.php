<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensitive_action_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->timestamp('confirmed_at');
            $table->timestamp('expires_at');
            $table->ipAddress('ip')->nullable();

            $table->index(['user_id', 'action', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_action_confirmations');
    }
};
