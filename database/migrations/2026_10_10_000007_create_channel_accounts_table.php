<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            // Clave de config/channels.php (twilio, cloud…): solo ChannelManager la interpreta.
            $table->string('provider', 30);
            $table->string('display_name')->nullable();
            // Remitente: número en E.164 para WhatsApp/SMS.
            $table->string('sender', 40);
            // Cifradas (cast encrypted:array). Nunca salen por la API.
            $table->text('credentials')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('quality_rating', 20)->nullable();
            $table->string('messaging_tier', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_accounts');
    }
};
