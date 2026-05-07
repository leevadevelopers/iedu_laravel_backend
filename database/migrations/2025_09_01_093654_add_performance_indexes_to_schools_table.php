<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexesToSchoolsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Composite index for common filter combinations
            $table->index(['tenant_id', 'status'], 'schools_tenant_status_index');
            $table->index(['tenant_id', 'school_type'], 'schools_tenant_type_index');
            $table->index(['tenant_id', 'country_code'], 'schools_tenant_country_index');
            $table->index(['tenant_id', 'state_province'], 'schools_tenant_state_index');

            // Index for search (MySQL uses prefix length for utf8mb4 key limits; SQLite uses full columns)
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->index(['tenant_id', 'official_name'], 'schools_tenant_name_index');
                $table->index(['tenant_id', 'official_name', 'created_at'], 'schools_tenant_name_created_index');
            } else {
                DB::statement('CREATE INDEX `schools_tenant_name_index` ON `schools` (`tenant_id`, `official_name`(100))');
                DB::statement('CREATE INDEX `schools_tenant_name_created_index` ON `schools` (`tenant_id`, `official_name`(100), `created_at`)');
            }
            $table->index(['tenant_id', 'school_code'], 'schools_tenant_code_index');

            // Index for current enrollment queries
            $table->index(['tenant_id', 'current_enrollment'], 'schools_tenant_enrollment_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex('schools_tenant_status_index');
            $table->dropIndex('schools_tenant_type_index');
            $table->dropIndex('schools_tenant_country_index');
            $table->dropIndex('schools_tenant_state_index');
            $table->dropIndex('schools_tenant_name_index');
            $table->dropIndex('schools_tenant_code_index');
            $table->dropIndex('schools_tenant_name_created_index');
            $table->dropIndex('schools_tenant_enrollment_index');
        });
    }
}
