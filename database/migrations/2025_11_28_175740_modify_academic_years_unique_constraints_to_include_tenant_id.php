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
        Schema::table('academic_years', function (Blueprint $table) {
            // Drop existing unique constraints
            $table->dropUnique(['school_id', 'code']);
            $table->dropUnique(['school_id', 'year']);
        });

        Schema::table('academic_years', function (Blueprint $table) {
            // Add new unique constraints that include tenant_id
            $table->unique(['school_id', 'tenant_id', 'code'], 'academic_years_school_tenant_code_unique');
            $table->unique(['school_id', 'tenant_id', 'year'], 'academic_years_school_tenant_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            // Drop the new constraints
            $table->dropUnique('academic_years_school_tenant_code_unique');
            $table->dropUnique('academic_years_school_tenant_year_unique');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            // Restore original constraints
            $table->unique(['school_id', 'code'], 'academic_years_school_id_code_unique');
            $table->unique(['school_id', 'year'], 'academic_years_school_id_year_unique');
        });
    }
};
