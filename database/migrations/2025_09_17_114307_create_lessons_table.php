<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');

            // Lesson Details
            $table->string('title'); // "Equações de 2º grau"
            $table->text('description')->nullable();
            $table->json('objectives')->nullable(); // Learning objectives

            // Associations
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->foreignId('academic_term_id')->constrained('academic_terms');

            // Timing
            $table->date('lesson_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes'); // Calculated field

            // Location and Format
            $table->string('classroom', 50)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_meeting_url', 500)->nullable();
            $table->json('online_meeting_details')->nullable(); // Zoom ID, password, etc.

            // Status and Progress
            $table->enum('status', [
                'scheduled', 'in_progress', 'completed',
                'cancelled', 'postponed', 'absent_teacher'
            ])->default('scheduled');
            $table->enum('type', [
                'regular', 'makeup', 'extra', 'review',
                'exam', 'practical', 'field_trip'
            ])->default('regular');

            // Content and Curriculum
            $table->text('content_summary')->nullable(); // What was taught
            $table->json('curriculum_topics')->nullable(); // Topics covered
            $table->text('homework_assigned')->nullable();
            $table->date('homework_due_date')->nullable();

            // Attendance and Participation
            $table->integer('expected_students')->default(0);
            $table->integer('present_students')->default(0);
            $table->decimal('attendance_rate', 5, 2)->nullable(); // Calculated

            // Teacher Notes and Observations
            $table->text('teacher_notes')->nullable();
            $table->text('lesson_observations')->nullable();
            $table->json('student_participation')->nullable(); // Individual observations

            // Workflow Integration
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['school_id', 'lesson_date']);
            $table->index(['teacher_id', 'lesson_date']);
            $table->index(['class_id', 'lesson_date']);
            $table->index(['subject_id', 'lesson_date']);
            $table->index(['academic_term_id']);
            $table->index(['status', 'type']);
            $table->index(['lesson_date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
