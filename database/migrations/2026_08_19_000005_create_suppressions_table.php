<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('identifier');
            $table->string('reason');
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'identifier'], 'suppressions_unique');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'channel', 'identifier'], 'suppressions_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppressions');
    }
};
