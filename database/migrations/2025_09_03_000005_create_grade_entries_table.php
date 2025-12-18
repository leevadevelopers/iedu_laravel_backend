<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('academic_term_id')->constrained('academic_terms')->onDelete('cascade');

            // Assessment Information
            // Add assessment_id foreign key (nullable for backward compatibility with assessment_name)
            $table->foreignId('assessment_id')
                  ->nullable()
                  ->constrained('assessments')
                  ->onDelete('set null');
            
            $table->string('assessment_name', 255);
            $table->enum('assessment_type', [
                'formative', 'summative', 'project', 'participation', 'homework', 'quiz', 'exam'
            ]);
            $table->date('assessment_date');

            // Grade Information
            $table->decimal('raw_score', 6, 2)->nullable();
            $table->decimal('percentage_score', 5, 2)->nullable();
            $table->string('letter_grade', 5)->nullable();
            $table->decimal('points_earned', 6, 2)->nullable();
            $table->decimal('points_possible', 6, 2)->nullable();

            // Weighting & Categories
            $table->string('grade_category', 100)->nullable();
            $table->decimal('weight', 5, 2)->default(1.00);

            // Tracking & Audit
            $table->foreignId('entered_by')->constrained('teachers');
            $table->timestamp('entered_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->foreignId('modified_by')->nullable()->constrained('teachers');
            $table->timestamp('modified_at')->nullable();

            // Comments & Notes
            $table->text('teacher_comments')->nullable();
            $table->text('private_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['student_id', 'academic_term_id']);
            $table->index(['class_id', 'assessment_date']);
            $table->index(['school_id', 'academic_term_id']);
            $table->index(['entered_by', 'entered_at']);
            $table->index(['tenant_id']);
            $table->index(['school_id', 'tenant_id']);
            // Indexes for assessment_id (added in consolidated migration)
            $table->index(['assessment_id', 'student_id']);
            $table->index(['assessment_id', 'class_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_entries');
    }
};
