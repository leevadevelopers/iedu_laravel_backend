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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('school_id');

            // Student Identity
            $table->string('student_number', 50);
            $table->string( 'first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);

            // Personal Information
            $table->date('date_of_birth');
            $table->string('birth_place')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('nationality', 100)->nullable();

            // Contact Information
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->json('address_json')->nullable();
            
            // Academic Information
            $table->date('admission_date');
            $table->string('current_grade_level', 20);
            $table->unsignedBigInteger('current_academic_year_id')->nullable();
            $table->enum('enrollment_status', ['enrolled', 'transferred', 'graduated', 'withdrawn', 'suspended'])->default('enrolled');
            $table->enum('status', ['draft', 'active', 'archived'])->default('active');
            $table->date('expected_graduation_date')->nullable();

            // Educational Profile
            $table->json('learning_profile_json')->nullable();
            $table->json('accommodation_needs_json')->nullable();
            $table->json('language_profile_json')->nullable();

            // Health & Safety
            $table->json('medical_information_json')->nullable();
            $table->json('emergency_contacts_json')->nullable();
            $table->json('special_circumstances_json')->nullable();

            // Performance Indicators (Cached)
            $table->decimal('current_gpa', 3, 2)->nullable();
            $table->decimal('attendance_rate', 5, 2)->nullable();
            $table->integer('behavioral_points')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');

            // Indexes
            $table->unique(['school_id', 'student_number']);
            $table->index('school_id');
            $table->index('current_grade_level');
            $table->index('enrollment_status');
            $table->index('status');
            $table->index(['last_name', 'first_name']);

            // Full-text search
            $table->fullText(['first_name', 'last_name', 'student_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
