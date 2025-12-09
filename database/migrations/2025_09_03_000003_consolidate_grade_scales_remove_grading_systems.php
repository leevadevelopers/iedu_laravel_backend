<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration consolidates GradingSystem data into GradeScale
     * and removes the redundant GradingSystem table.
     */
    public function up(): void
    {
        // Check if grade_scales table exists (for migrate:fresh vs migrate scenarios)
        if (!Schema::hasTable('grade_scales')) {
            // Table doesn't exist yet, migration original will create it correctly
            // Just drop grading_systems if it exists
            Schema::dropIfExists('grading_systems');
            return;
        }

        // Table exists - we need to migrate existing data
        
        // Step 1: Add new columns to grade_scales if they don't exist
        if (!Schema::hasColumn('grade_scales', 'status')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('is_default');
            });
        }

        if (!Schema::hasColumn('grade_scales', 'configuration_json')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->json('configuration_json')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('grade_scales', 'code')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->string('code', 50)->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('grade_scales', 'description')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->text('description')->nullable()->after('code');
            });
        }

        if (!Schema::hasColumn('grade_scales', 'min_value')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->decimal('min_value', 5, 2)->nullable()->after('description');
            });
        }

        if (!Schema::hasColumn('grade_scales', 'max_value')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->decimal('max_value', 5, 2)->nullable()->after('min_value');
            });
        }

        if (!Schema::hasColumn('grade_scales', 'passing_grade')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->decimal('passing_grade', 5, 2)->nullable()->after('max_value');
            });
        }

        // Step 2: Migrate data from grading_systems to grade_scales (only if grading_systems exists)
        if (Schema::hasTable('grading_systems') && Schema::hasColumn('grade_scales', 'grading_system_id')) {
            // Copy configuration_json and status from grading_systems to their grade_scales
            DB::statement("
                UPDATE grade_scales gs
                INNER JOIN grading_systems gsys ON gs.grading_system_id = gsys.id
                SET 
                    gs.status = gsys.status,
                    gs.configuration_json = gsys.configuration_json,
                    gs.is_default = CASE WHEN gsys.is_primary = 1 THEN 1 ELSE gs.is_default END
                WHERE gs.grading_system_id IS NOT NULL
            ");

            // Update scale_type from grading_system if scale_type is null
            DB::statement("
                UPDATE grade_scales gs
                INNER JOIN grading_systems gsys ON gs.grading_system_id = gsys.id
                SET gs.scale_type = CASE 
                    WHEN gsys.system_type = 'traditional_letter' THEN 'letter'
                    WHEN gsys.system_type = 'percentage' THEN 'percentage'
                    WHEN gsys.system_type = 'points' THEN 'points'
                    WHEN gsys.system_type = 'standards_based' THEN 'standards'
                    ELSE gs.scale_type
                END
                WHERE gs.scale_type IS NULL OR gs.scale_type = ''
            ");

            // Step 3: Drop foreign key constraint if it exists
            try {
                Schema::table('grade_scales', function (Blueprint $table) {
                    $table->dropForeign(['grading_system_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }

            // Step 4: Remove grading_system_id column if it exists
            if (Schema::hasColumn('grade_scales', 'grading_system_id')) {
                Schema::table('grade_scales', function (Blueprint $table) {
                    $table->dropColumn('grading_system_id');
                });
            }
        }

        // Step 5: Drop grading_systems table if it exists
        Schema::dropIfExists('grading_systems');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate grading_systems table
        Schema::create('grading_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name', 255);
            $table->enum('system_type', [
                'traditional_letter', 'percentage', 'points', 'standards_based', 'narrative'
            ]);
            $table->json('applicable_grades')->nullable();
            $table->json('applicable_subjects')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('configuration_json')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['school_id']);
            $table->index(['system_type']);
            $table->index(['tenant_id']);
            $table->index(['school_id', 'tenant_id']);
        });

        // Add grading_system_id back to grade_scales
        if (Schema::hasTable('grade_scales') && !Schema::hasColumn('grade_scales', 'grading_system_id')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->foreignId('grading_system_id')->nullable()->after('id');
                $table->foreign('grading_system_id')->references('id')->on('grading_systems')->onDelete('cascade');
                $table->index(['grading_system_id']);
            });
        }

        // Remove added columns (optional - you may want to keep them)
        if (Schema::hasTable('grade_scales')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                if (Schema::hasColumn('grade_scales', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('grade_scales', 'configuration_json')) {
                    $table->dropColumn('configuration_json');
                }
                if (Schema::hasColumn('grade_scales', 'code')) {
                    $table->dropColumn('code');
                }
                if (Schema::hasColumn('grade_scales', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('grade_scales', 'min_value')) {
                    $table->dropColumn('min_value');
                }
                if (Schema::hasColumn('grade_scales', 'max_value')) {
                    $table->dropColumn('max_value');
                }
                if (Schema::hasColumn('grade_scales', 'passing_grade')) {
                    $table->dropColumn('passing_grade');
                }
            });
        }
    }
};
