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
        Schema::create('student_enrollment_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_year_id');
            
            // Enrollment Details
            $table->date('enrollment_date');
            $table->date('withdrawal_date')->nullable();
            $table->string('grade_level_at_enrollment', 20);
            $table->string('grade_level_at_withdrawal', 20)->nullable();
            
            // Status Information
            $table->enum('enrollment_type', ['new', 'transfer_in', 're_enrollment']);
            $table->enum('withdrawal_type', ['graduation', 'transfer_out', 'dropout', 'other'])->nullable();
            $table->string('withdrawal_reason')->nullable();
            
            // Previous/Next School Information
            $table->string('previous_school', 255)->nullable();
            $table->string('next_school', 255)->nullable();
            $table->json('transfer_documents_json')->nullable();
            
            // Academic Status at Time
            $table->decimal('final_gpa', 3, 2)->nullable();
            $table->decimal('credits_earned', 6, 2)->nullable();
            $table->json('academic_records_json')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            
            // Indexes
            $table->index('school_id');
            $table->index('student_id');
            $table->index('academic_year_id');
            $table->index('enrollment_date');
            $table->index('enrollment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollment_history');
    }
};
