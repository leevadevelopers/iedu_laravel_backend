<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchoolEntitiesTables extends Migration
{
    public function up()
    {
        // Create student_parents table
        Schema::create('student_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->enum('relationship_type', ['father', 'mother', 'guardian', 'other'])->default('other');
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('can_pickup')->default(false);
            $table->json('communication_preferences')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_primary_contact']);
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Create school_classes table
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('class_name');
            $table->string('class_code')->unique();
            $table->string('grade_level');
            $table->string('academic_year');
            $table->unsignedBigInteger('teacher_id');
            $table->string('room_number')->nullable();
            $table->integer('capacity')->default(30);
            $table->integer('current_enrollment')->default(0);
            $table->json('schedule')->nullable();
            $table->json('subjects')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'grade_level']);
            $table->index(['tenant_id', 'academic_year']);
            $table->index(['tenant_id', 'teacher_id']);
            $table->foreign('teacher_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Create students table
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('student_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->date('enrollment_date');
            $table->date('graduation_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'graduated', 'transferred', 'suspended'])->default('active');
            $table->string('grade_level');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('academic_year');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'grade_level']);
            $table->index(['tenant_id', 'class_id']);
            $table->index(['tenant_id', 'academic_year']);
            $table->index(['student_code']);
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('student_parents')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('student_parents');
    }
}
