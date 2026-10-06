<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('name');
            $table->jsonb('attributes')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'phone']);
            $table->unique(['tenant_id', 'email']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
