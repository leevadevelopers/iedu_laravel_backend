<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');

            // Conflict Details
            $table->enum('conflict_type', [
                'teacher_double_booking', 'classroom_double_booking',
                'student_schedule_overlap', 'resource_conflict',
                'time_constraint_violation', 'capacity_exceeded'
            ]);
            $table->string('conflict_description');

            // Related Schedules
            $table->json('conflicting_schedule_ids'); // Array of schedule IDs
            $table->json('affected_entities'); // Teachers, classrooms, students affected

            // Conflict Details
            $table->date('conflict_date');
            $table->time('conflict_start_time');
            $table->time('conflict_end_time');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');

            // Resolution
            $table->enum('status', ['detected', 'acknowledged', 'resolved', 'ignored'])->default('detected');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();

            // Detection Information
            $table->enum('detection_method', ['automatic', 'manual', 'report'])->default('automatic');
            $table->foreignId('detected_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['school_id', 'conflict_type']);
            $table->index(['conflict_date', 'status']);
            $table->index(['severity', 'status']);
            $table->index(['detection_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_conflicts');
    }
};
