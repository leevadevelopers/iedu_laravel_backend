<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

            // Index for search functionality - shorten key with prefix length to fit InnoDB limits
            // Laravel's schema builder does not support prefix lengths in index() directly.
            // We'll add a raw index for official_name(100) to keep under 1000-byte limit with utf8mb4.
            Schema::getConnection()->statement('CREATE INDEX `schools_tenant_name_index` ON `schools` (`tenant_id`, `official_name`(100))');
            $table->index(['tenant_id', 'school_code'], 'schools_tenant_code_index');

            // Index for ordering with prefix on official_name
            Schema::getConnection()->statement('CREATE INDEX `schools_tenant_name_created_index` ON `schools` (`tenant_id`, `official_name`(100), `created_at`)');

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
