<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedule_conflicts', function (Blueprint $table) {
            // Add tenant_id column
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
        });


        // Make tenant_id not nullable after populating
        Schema::table('schedule_conflicts', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });

        // Add composite index for performance
        Schema::table('schedule_conflicts', function (Blueprint $table) {
            $table->index(['tenant_id', 'school_id'], 'schedule_conflicts_tenant_school_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_conflicts', function (Blueprint $table) {
            $table->dropIndex('schedule_conflicts_tenant_school_index');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
