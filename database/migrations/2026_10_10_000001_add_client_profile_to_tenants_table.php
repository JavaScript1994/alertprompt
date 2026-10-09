<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('document_type')->nullable()->after('type');
            $table->string('document_number', 20)->nullable()->unique()->after('document_type');
            $table->string('contact_email')->nullable()->after('document_number');
            $table->string('contact_phone', 30)->nullable()->after('contact_email');
            $table->string('address')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['document_number']);
            $table->dropColumn(['document_type', 'document_number', 'contact_email', 'contact_phone', 'address']);
        });
    }
};
