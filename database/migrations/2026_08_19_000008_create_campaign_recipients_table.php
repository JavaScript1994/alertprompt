<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->string('error_code')->nullable();
            $table->string('skip_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // Idempotencia: evita duplicados al reanudar campaña caída
            $table->unique(['campaign_id', 'contact_id'], 'campaign_recipients_unique');
            // El webhook llega con provider_message_id — sin este índice es full scan
            $table->index('provider_message_id', 'campaign_recipients_provider_msg_idx');
            $table->index(['campaign_id', 'status'], 'campaign_recipients_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
