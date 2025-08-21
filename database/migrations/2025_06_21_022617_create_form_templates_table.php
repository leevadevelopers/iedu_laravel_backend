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
                'project_creation', 'contract_management', 'procurement', 
                'monitoring', 'financial', 'custom'
            ]);
            $table->enum('methodology_type', [
                'universal', 'usaid', 'world_bank', 'eu', 'custom'
            ])->default('universal');
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
            $table->index(['methodology_type', 'is_active']);
            $table->index(['tenant_id', 'is_default']);
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_templates');
    }
}