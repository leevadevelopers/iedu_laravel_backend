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
                'student_enrollment', 'student_registration', 'student_transfer',
                'attendance', 'grades', 'academic_records', 'behavior_incident',
                'parent_communication', 'teacher_evaluation', 'curriculum_planning',
                'extracurricular', 'field_trip', 'parent_meeting', 'student_health',
                'special_education', 'discipline', 'graduation', 'scholarship',

                // // Legacy Project Categories
                // 'project_creation', 'contract_management', 'procurement',
                // 'monitoring', 'financial', 'custom',
                // 'project', 'planning', 'execution', 'closure',
                // 'risk_assessment', 'risk_mitigation', 'risk_monitoring', 'risk_report',
                // 'indicator', 'evaluation', 'monitoring_report', 'dashboard',
                // 'budget', 'transaction', 'expense', 'financial_report', 'audit',
                // 'procurement_request', 'tender', 'contract', 'procurement_evaluation', 'supplier'
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
