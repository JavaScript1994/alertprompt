<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_recipient_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->jsonb('payload')->default('{}');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('campaign_recipient_id');
            $table->index(['campaign_recipient_id', 'event'], 'message_events_event_idx');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_events');
    }
};
