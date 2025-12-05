<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('academic_term_id')->nullable()->constrained('academic_terms')->onDelete('set null');

            // Class Identity
            $table->string('name', 255); // "7th Grade Mathematics - Section A"
            $table->string('section', 10)->nullable(); // "A", "B", "Advanced"
            $table->string('class_code', 50)->nullable();

            // Class Configuration
            $table->string('grade_level', 20);
            $table->integer('max_students')->default(30);
            $table->integer('current_enrollment')->default(0);

            // Teacher Assignment
            $table->foreignId('primary_teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
            $table->json('additional_teachers_json')->nullable(); // Co-teachers, assistants

            // Schedule Information
            $table->json('schedule_json')->nullable(); // Days, periods, room assignments
            $table->string('room_number', 50)->nullable();

            // Status
            $table->enum('status', ['draft', 'planned', 'active', 'completed', 'cancelled', 'archived'])->default('planned');

            $table->timestamps();

            // Indexes
            $table->index(['school_id']);
            $table->index(['subject_id']);
            $table->index(['academic_year_id']);
            $table->index(['primary_teacher_id']);
            $table->index(['grade_level']);
            $table->index(['tenant_id']);
            $table->index(['school_id', 'tenant_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('classes');
    }
};
