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
        // Check if tenant_id column already exists
        if (!Schema::hasColumn('schedule_conflicts', 'tenant_id')) {
            Schema::table('schedule_conflicts', function (Blueprint $table) {
                // Add tenant_id column
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
            });
        }

        // Make tenant_id not nullable after populating (if it's still nullable)
        $column = DB::select("SHOW COLUMNS FROM schedule_conflicts WHERE Field = 'tenant_id'");
        if (!empty($column) && $column[0]->Null === 'YES') {
            Schema::table('schedule_conflicts', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }

        // Add composite index for performance (only if it doesn't exist)
        $indexes = DB::select("SHOW INDEXES FROM schedule_conflicts WHERE Key_name = 'schedule_conflicts_tenant_school_index'");
        if (empty($indexes)) {
            Schema::table('schedule_conflicts', function (Blueprint $table) {
                $table->index(['tenant_id', 'school_id'], 'schedule_conflicts_tenant_school_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key first (before index)
        Schema::table('schedule_conflicts', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        // Drop the composite index only if it exists and isn't used by other constraints
        // Note: The index might be used by the school_id foreign key, so we check first
        $indexes = DB::select("SHOW INDEXES FROM schedule_conflicts WHERE Key_name = 'schedule_conflicts_tenant_school_index'");
        if (!empty($indexes)) {
            try {
                DB::statement('ALTER TABLE schedule_conflicts DROP INDEX schedule_conflicts_tenant_school_index');
            } catch (\Exception $e) {
                // Index is used by other constraints (e.g., school_id foreign key), leave it
                // This is safe - the index will remain but tenant_id will be removed
            }
        }

        // Drop the column
        Schema::table('schedule_conflicts', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
