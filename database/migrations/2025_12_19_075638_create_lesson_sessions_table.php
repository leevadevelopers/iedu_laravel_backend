<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lesson_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            
            // Link to schedule (nullable for ad-hoc lessons)
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->onDelete('set null');
            
            // Core associations
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            
            // Timing
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->boolean('is_scheduled')->default(true);
            
            // Status
            $table->enum('status', ['in_progress', 'completed', 'cancelled'])->default('in_progress');
            
            // Lesson Info
            $table->text('lesson_note')->nullable();
            $table->string('audio_note_url', 500)->nullable();
            $table->integer('audio_duration')->nullable(); // in seconds
            $table->json('lesson_tags')->nullable(); // ["new_topic", "review", "homework", "test"]
            
            // Attendance Stats (calculated on completion)
            $table->integer('students_present')->default(0);
            $table->integer('students_absent')->default(0);
            $table->integer('students_late')->default(0);
            $table->integer('students_unmarked')->default(0);
            $table->decimal('attendance_completion_rate', 5, 2)->default(0.00);
            
            // Behavior Stats
            $table->integer('total_behavior_points')->default(0);
            $table->integer('positive_behavior_count')->default(0);
            $table->integer('negative_behavior_count')->default(0);
            
            // Metadata
            $table->string('device_id', 255)->nullable(); // for offline sync tracking
            $table->timestamp('synced_at')->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index('teacher_id');
            $table->index('class_id');
            $table->index('started_at');
            $table->index('status');
            $table->index(['school_id', 'started_at']);
            $table->index(['teacher_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_sessions');
    }
};
