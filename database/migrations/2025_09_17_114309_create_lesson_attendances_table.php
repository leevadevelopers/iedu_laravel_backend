<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');

            // Attendance Status
            $table->enum('status', [
                'present', 'absent', 'late', 'excused',
                'left_early', 'partial', 'online_present'
            ])->default('present');

            // Timing Information
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->integer('minutes_late')->nullable(); // Calculated
            $table->integer('minutes_present')->nullable(); // For partial attendance

            // Attendance Method
            $table->enum('marked_by_method', [
                'teacher_manual', 'qr_code', 'student_self_checkin',
                'automatic_online', 'biometric', 'rfid'
            ])->default('teacher_manual');

            // Additional Information
            $table->text('notes')->nullable(); // Reason for absence, etc.
            $table->boolean('notified_parent')->default(false);
            $table->timestamp('parent_notified_at')->nullable();

            // Location Data (for verification)
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->string('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Workflow Integration
            $table->boolean('requires_approval')->default(false);
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Audit
            $table->foreignId('marked_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Unique constraint
            $table->unique(['lesson_id', 'student_id']);

            // Indexes
            $table->index(['school_id', 'lesson_id']);
            $table->index(['student_id', 'status']);
            $table->index(['lesson_id', 'status']);
            $table->index(['marked_by_method']);
            $table->index(['requires_approval', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_attendances');
    }
};
