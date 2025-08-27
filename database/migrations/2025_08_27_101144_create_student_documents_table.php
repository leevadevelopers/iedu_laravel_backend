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
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            
            // Document Information
            $table->string('document_name');
            $table->enum('document_type', [
                'birth_certificate', 'vaccination_records', 'previous_transcripts',
                'identification', 'medical_records', 'special_education',
                'enrollment_form', 'emergency_contacts', 'photo_permission',
                'other'
            ]);
            $table->string('document_category', 100)->nullable();
            
            // File Information
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 10);
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            
            // Document Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->date('expiration_date')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('verified')->default(false);
            
            // Processing Information
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            // Privacy & Access
            $table->json('access_permissions_json')->nullable();
            $table->boolean('ferpa_protected')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('school_id');
            $table->index('student_id');
            $table->index('document_type');
            $table->index('status');
            $table->index('required');
            $table->index('expiration_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
