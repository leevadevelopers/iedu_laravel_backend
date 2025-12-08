<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->onDelete('set null');
            $table->foreignId('academic_term_id')->nullable()->constrained('academic_terms')->onDelete('set null');

            // Basic Schedule Information
            $table->string('name'); // "Matemática - 7º A - Manhã"
            $table->text('description')->nullable();

            // Associations
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
            $table->string('classroom', 50)->nullable(); // Sala de aula

            // Time Configuration
            $table->enum('period', ['morning', 'afternoon', 'evening', 'night'])->default('morning');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');

            // Recurrence Configuration
            $table->date('start_date');
            $table->date('end_date');
            $table->json('recurrence_pattern')->nullable(); // Weekly, biweekly, etc.

            // Status and Configuration
            $table->enum('status', ['active', 'suspended', 'cancelled', 'completed'])->default('active');
            $table->boolean('is_online')->default(false);
            $table->string('online_meeting_url', 500)->nullable();
            $table->json('configuration_json')->nullable(); // Additional settings

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['teacher_id', 'day_of_week', 'start_time']);
            $table->index(['class_id', 'subject_id']);
            $table->index(['day_of_week', 'period']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');

            // Unique constraint to prevent conflicts (only when teacher_id is set)
            // Note: MySQL unique constraints with NULL values allow multiple NULLs
            // So this will only enforce uniqueness when teacher_id is not null
            $table->unique([
                'school_id', 'teacher_id', 'day_of_week',
                'start_time', 'end_time', 'start_date', 'end_date'
            ], 'unique_teacher_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
