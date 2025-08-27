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
        Schema::create('family_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('guardian_user_id');
            
            // Relationship Details
            $table->enum('relationship_type', [
                'mother', 'father', 'stepmother', 'stepfather',
                'grandmother', 'grandfather', 'aunt', 'uncle',
                'guardian', 'foster_parent', 'other'
            ]);
            $table->string('relationship_description', 100)->nullable();
            
            // Contact Permissions
            $table->boolean('primary_contact')->default(false);
            $table->boolean('emergency_contact')->default(false);
            $table->boolean('pickup_authorized')->default(false);
            $table->boolean('academic_access')->default(true);
            $table->boolean('medical_access')->default(false);
            
            // Legal Information
            $table->boolean('custody_rights')->default(false);
            $table->json('custody_details_json')->nullable();
            $table->boolean('financial_responsibility')->default(false);
            
            // Communication Preferences
            $table->json('communication_preferences_json')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('guardian_user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index('school_id');
            $table->index('student_id');
            $table->index('guardian_user_id');
            $table->index('relationship_type');
            $table->index('primary_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_relationships');
    }
};
