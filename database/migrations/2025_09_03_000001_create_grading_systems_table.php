<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Skip creating grading_systems table - it's being consolidated into grade_scales
        // This migration is kept for historical reference but won't create the table
        if (Schema::hasTable('grading_systems')) {
            return; // Table already exists from previous migration
        }
        
        // Don't create - will be removed by consolidation migration
        return;
        
        /* Original migration - commented out
        Schema::create('grading_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // System Identity
            $table->string('name', 255);
            $table->enum('system_type', [
                'traditional_letter', 'percentage', 'points', 'standards_based', 'narrative'
            ]);

            // Applicability
            $table->json('applicable_grades')->nullable(); // Grade levels using this system
            $table->json('applicable_subjects')->nullable(); // Subjects using this system
            $table->boolean('is_primary')->default(false);

            // Configuration
            $table->json('configuration_json')->nullable(); // System-specific settings

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            // Indexes
            $table->index(['school_id']);
            $table->index(['system_type']);
            $table->index(['tenant_id']);
            $table->index(['school_id', 'tenant_id']);
        });
        */
    }

    public function down()
    {
        Schema::dropIfExists('grading_systems');
    }
};
