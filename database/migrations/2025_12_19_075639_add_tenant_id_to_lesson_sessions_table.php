<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lesson_sessions', function (Blueprint $table) {
            // Add tenant_id similar to other multi-tenant tables
            $table->foreignId('tenant_id')
                ->after('id')
                ->constrained('tenants')
                ->onDelete('cascade');

            // Useful composite index for queries
            $table->index(['tenant_id', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'school_id']);
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};


