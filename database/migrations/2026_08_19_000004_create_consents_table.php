<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('source');
            $table->ipAddress('ip');
            $table->text('evidence_text');
            $table->timestamps();

            $table->index('contact_id');
            $table->index(['contact_id', 'channel', 'revoked_at'], 'consents_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
