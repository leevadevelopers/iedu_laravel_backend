#!/bin/bash

# Student Information System (SIS) - Database Migration
# This script creates the database tables for the Student Information System

# Generate sequential timestamps starting from current time
CURRENT_TIME=$(date +%s)
BASE_TIMESTAMP=$(date -d "@$CURRENT_TIME" +%Y_%m_%d_%H%M%S)
TIMESTAMP_1=$(date -d "@$((CURRENT_TIME + 1))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_2=$(date -d "@$((CURRENT_TIME + 2))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_3=$(date -d "@$((CURRENT_TIME + 3))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_4=$(date -d "@$((CURRENT_TIME + 4))" +%Y_%m_%d_%H%M%S)

# Validate Laravel root
if [ ! -d "vendor" ]; then
    echo "❌ Error: Please run this script from the Laravel root directory (where vendor folder exists)"
    exit 1
fi

echo "🏗️ Creating SIS database migrations..."

# Create migration for students table
cat > "database/migrations/${TIMESTAMP_1}_create_students_table.php" << 'EOF'
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
            $table->unsignedBigInteger('school_id');
            
            // Student Identity
            $table->string('student_number', 50);
            $table->string('government_id', 50)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('preferred_name', 100)->nullable();
            
            // Personal Information
            $table->date('date_of_birth');
            $table->string('birth_place')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
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
EOF

# Create migration for family relationships table
cat > "database/migrations/${TIMESTAMP_2}_create_family_relationships_table.php" << 'EOF'
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
EOF

# Create migration for student enrollment history
cat > "database/migrations/${TIMESTAMP_3}_create_student_enrollment_history_table.php" << 'EOF'
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
EOF

# Create migration for student documents
cat > "database/migrations/${TIMESTAMP_4}_create_student_documents_table.php" << 'EOF'
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
EOF

echo "✅ SIS database migrations created successfully!"
echo "📁 Migration files:"
echo "   - ${TIMESTAMP_1}_create_students_table.php"
echo "   - ${TIMESTAMP_2}_create_family_relationships_table.php"
echo "   - ${TIMESTAMP_3}_create_student_enrollment_history_table.php"
echo "   - ${TIMESTAMP_4}_create_student_documents_table.php"
echo ""
echo "🚀 Run 'php artisan migrate' to apply the migrations"