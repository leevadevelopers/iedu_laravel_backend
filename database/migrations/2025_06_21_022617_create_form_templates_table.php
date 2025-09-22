<?php

// =====================================================
// MIGRATIONS
// =====================================================

// File: database/migrations/2024_01_01_100001_create_form_templates_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormTemplatesTable extends Migration
{
    public function up()
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 10)->default('1.0');
            $table->enum('category', [
                // School Management Categories
                'school_registration', 'school_enrollment', 'school_setup',
                'student_enrollment', 'student_registration', 'student_transfer',
                'attendance', 'grades', 'academic_records', 'behavior_incident',
                'parent_communication', 'teacher_evaluation', 'curriculum_planning',
                'extracurricular', 'field_trip', 'parent_meeting', 'student_health',
                'special_education', 'discipline', 'graduation', 'scholarship',
                'staff_management', 'faculty_recruitment', 'professional_development',
                'school_calendar', 'events_management', 'facilities_management',
                'transportation', 'cafeteria_management', 'library_management',
                'technology_management', 'security_management', 'maintenance_requests',
                'financial_aid', 'tuition_management', 'donation_management',
                'alumni_relations', 'community_outreach', 'partnership_management',
                'document_upload', 'academic_year_setup'
            ]);
            $table->string('estimated_completion_time')->nullable();
            $table->boolean('is_multi_step')->default(false);
            $table->boolean('auto_save')->default(true);
            $table->enum('compliance_level', ['basic', 'standard', 'strict', 'custom'])->default('standard');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->json('form_configuration');
            $table->json('steps');
            $table->json('form_triggers')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('workflow_configuration')->nullable();
            $table->json('ai_prompts')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'category', 'is_active']);
            $table->index(['tenant_id', 'is_default']);
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_templates');
    }
}
