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
        // Modify the school_type enum column to ensure it has all correct values
        // MySQL requires the full enum definition when modifying
        DB::statement("ALTER TABLE `schools` MODIFY COLUMN `school_type` ENUM(
            'pre_primary',
            'primary',
            'secondary_general',
            'technical_professional',
            'institute_medio',
            'higher_education',
            'teacher_training',
            'adult_education',
            'special_needs'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous enum definition if needed
        // Note: This assumes the previous enum had the same values
        // If the previous enum was different, you may need to adjust this
        DB::statement("ALTER TABLE `schools` MODIFY COLUMN `school_type` ENUM(
            'pre_primary',
            'primary',
            'secondary_general',
            'technical_professional',
            'institute_medio',
            'higher_education',
            'teacher_training',
            'adult_education',
            'special_needs'
        ) NOT NULL");
    }
};
