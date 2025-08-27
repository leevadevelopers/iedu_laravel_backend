<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchoolsTable extends Migration
{
    public function up()
    {
        // Create schools table (Multi-tenant root for educational institutions)
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // School Identity
            $table->string('school_code', 50)->unique();
            $table->string('official_name', 255);
            $table->string('display_name', 255);
            $table->string('short_name', 50);

            // Educational Classification
            $table->enum('school_type', [
                'public', 'private', 'charter', 'magnet', 'international',
                'vocational', 'special_needs', 'alternative'
            ]);
            $table->json('educational_levels'); // ['elementary', 'middle', 'high']
            $table->string('grade_range_min', 10); // 'K', 'Pre-K', '1', etc.
            $table->string('grade_range_max', 10); // '5', '8', '12', etc.

            // Contact Information
            $table->string('email', 255);
            $table->string('phone', 50)->nullable();
            $table->string('website', 255)->nullable();
            $table->json('address_json')->nullable(); // Structured address

            // Geographic & Regulatory
            $table->char('country_code', 2);
            $table->string('state_province', 100)->nullable();
            $table->string('city', 100);
            $table->string('timezone', 50)->default('UTC');
            $table->string('ministry_education_code', 100)->nullable();
            $table->enum('accreditation_status', [
                'accredited', 'candidate', 'probation', 'not_accredited'
            ])->default('candidate');

            // Educational Configuration
            $table->enum('academic_calendar_type', [
                'semester', 'trimester', 'quarter', 'year_round', 'custom'
            ])->default('semester');
            $table->integer('academic_year_start_month')->default(8);
            $table->enum('grading_system', [
                'traditional_letter', 'percentage', 'points', 'standards_based',
                'narrative', 'mixed'
            ])->default('traditional_letter');
            $table->enum('attendance_tracking_level', [
                'daily', 'period', 'subject', 'flexible'
            ])->default('daily');

            // School Culture & Philosophy
            $table->text('educational_philosophy')->nullable();
            $table->json('language_instruction'); // Primary and additional languages
            $table->string('religious_affiliation', 100)->nullable();

            // Operational Configuration
            $table->unsignedInteger('student_capacity')->nullable();
            $table->unsignedInteger('current_enrollment')->default(0);
            $table->unsignedInteger('staff_count')->default(0);

            // Platform Configuration
            $table->enum('subscription_plan', [
                'basic', 'standard', 'premium', 'enterprise', 'custom'
            ])->default('basic');
            $table->json('feature_flags')->default('{}');
            $table->json('integration_settings')->default('{}');
            $table->json('branding_configuration')->default('{}');

            // Status & Lifecycle
            $table->enum('status', [
                'setup', 'active', 'maintenance', 'suspended', 'archived'
            ])->default('setup');
            $table->date('established_date')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('school_type');
            $table->index(['country_code', 'state_province']);
            $table->index('status');
            $table->index(['grade_range_min', 'grade_range_max']);

            // Full-text search
            $table->fullText(['official_name', 'display_name', 'short_name']);
        });

    }

    public function down()
    {
        Schema::dropIfExists('schools');
    }
}
