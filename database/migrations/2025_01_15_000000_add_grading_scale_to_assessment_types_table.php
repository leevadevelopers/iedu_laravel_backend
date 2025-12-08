<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds max_score and grading_scale columns if the table exists and the columns don't.
     * If the table doesn't exist, it will be created by the create_assessment_types_table migration.
     */
    public function up(): void
    {
        if (!Schema::hasTable('assessment_types')) {
            return; // Table will be created by create_assessment_types_table migration
        }

        $hasMaxScore = Schema::hasColumn('assessment_types', 'max_score');
        $hasGradingScale = Schema::hasColumn('assessment_types', 'grading_scale');

        if (!$hasMaxScore || !$hasGradingScale) {
            Schema::table('assessment_types', function (Blueprint $table) use ($hasMaxScore, $hasGradingScale) {
                // Add max_score column if it doesn't exist
                if (!$hasMaxScore) {
                    $table->decimal('max_score', 5, 2)->nullable()->after('default_weight');
                }
                
                // Add grading_scale column if it doesn't exist
                if (!$hasGradingScale) {
                    $afterColumn = $hasMaxScore ? 'max_score' : 'default_weight';
                    $table->enum('grading_scale', ['percentage', 'numeric'])
                          ->default('percentage')
                          ->after($afterColumn);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('assessment_types') && Schema::hasColumn('assessment_types', 'grading_scale')) {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->dropColumn('grading_scale');
            });
        }
        // Note: We don't drop max_score here as it might have been added by another migration
    }
};
