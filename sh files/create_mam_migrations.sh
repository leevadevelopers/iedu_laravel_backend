#!/bin/bash

# iEDU Academic Management - Database Migrations
# Creates all Laravel migrations for the Academic Management module

echo "🗄️ Creating iEDU Academic Management Migrations..."

# Create Teachers migration
cat > database/migrations/2024_01_05_000004_create_teachers_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Teacher Identity
            $table->string('employee_id', 50)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('preferred_name', 100)->nullable();
            $table->string('title', 20)->nullable(); // Mr., Mrs., Dr., Prof.

            // Personal Information
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->json('address_json')->nullable();

            // Professional Information
            $table->enum('employment_type', [
                'full_time', 'part_time', 'substitute', 'contract', 'volunteer'
            ])->default('full_time');
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated', 'on_leave'])->default('active');

            // Educational Background
            $table->json('education_json')->nullable(); // Degrees, certifications
            $table->json('certifications_json')->nullable(); // Teaching licenses, endorsements
            $table->json('specializations_json')->nullable(); // Subject areas, grade levels

            // Professional Details
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->json('schedule_json')->nullable(); // Work schedule, availability

            // Emergency Contacts
            $table->json('emergency_contacts_json')->nullable();

            // Additional Information
            $table->text('bio')->nullable();
            $table->string('profile_photo_path', 500)->nullable();
            $table->json('preferences_json')->nullable(); // Teaching preferences, communication settings

            $table->timestamps();

            // Indexes
            $table->index(['school_id']);
            $table->index(['user_id']);
            $table->index(['employee_id']);
            $table->index(['status']);
            $table->index(['employment_type']);
            $table->index(['department']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('teachers');
    }
};
EOF

# Create Subjects migration
cat > database/migrations/2024_01_04_000001_create_subjects_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');

            // Subject Identity
            $table->string('name', 255);
            $table->string('code', 50);
            $table->text('description')->nullable();

            // Academic Classification
            $table->enum('subject_area', [
                'mathematics', 'science', 'language_arts', 'social_studies',
                'foreign_language', 'arts', 'physical_education', 'technology',
                'vocational', 'other'
            ]);
            $table->json('grade_levels'); // Array of applicable grades

            // Academic Standards
            $table->json('learning_standards_json')->nullable(); // Curriculum standards alignment
            $table->json('prerequisites')->nullable(); // Required prior knowledge/courses

            // Configuration
            $table->decimal('credit_hours', 3, 1)->default(1.0);
            $table->boolean('is_core_subject')->default(false);
            $table->boolean('is_elective')->default(false);

            // Status
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');

            $table->timestamps();

            // Constraints
            $table->unique(['school_id', 'code']);

            // Indexes
            $table->index(['school_id']);
            $table->index(['subject_area']);
            $table->index(['status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subjects');
    }
};
EOF

# Create Classes migration
cat > database/migrations/2024_01_04_000002_create_classes_table.php << 'EOF'
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
            $table->enum('status', ['planned', 'active', 'completed', 'cancelled'])->default('planned');

            $table->timestamps();

            // Indexes
            $table->index(['school_id']);
            $table->index(['subject_id']);
            $table->index(['academic_year_id']);
            $table->index(['primary_teacher_id']);
            $table->index(['grade_level']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('classes');
    }
};
EOF

# Create Student Class Enrollments pivot table
cat > database/migrations/2024_01_04_000003_create_student_class_enrollments_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');

            $table->date('enrollment_date');
            $table->enum('status', ['active', 'dropped', 'completed'])->default('active');
            $table->string('final_grade', 5)->nullable();

            $table->timestamps();

            // Unique enrollment per student per class
            $table->unique(['student_id', 'class_id']);

            // Indexes
            $table->index(['student_id']);
            $table->index(['class_id']);
            $table->index(['status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_class_enrollments');
    }
};
EOF

# Create Grading Systems migration
cat > database/migrations/2024_01_05_000001_create_grading_systems_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grading_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');

            // System Identity
            $table->string('name', 255);
            $table->enum('system_type', [
                'traditional_letter', 'percentage', 'points', 'standards_based', 'narrative'
            ]);

            // Applicability
            $table->json('applicable_grades')->nullable(); // Grade levels using this system
            $table->json('applicable_subjects')->nullable(); // Subjects using this system
            $table->boolean('is_primary')->default(false);

            // Configuration
            $table->json('configuration_json')->nullable(); // System-specific settings

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            // Indexes
            $table->index(['school_id']);
            $table->index(['system_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grading_systems');
    }
};
EOF

# Create Grade Scales migration
cat > database/migrations/2024_01_05_000002_create_grade_scales_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_system_id')->constrained('grading_systems')->onDelete('cascade');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');

            // Scale Identity
            $table->string('name', 255);
            $table->enum('scale_type', ['letter', 'percentage', 'points', 'standards']);
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['grading_system_id']);
            $table->index(['school_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_scales');
    }
};
EOF

# Create Grade Levels migration
cat > database/migrations/2024_01_05_000003_create_grade_levels_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained('grade_scales')->onDelete('cascade');

            // Grade Definition
            $table->string('grade_value', 10); // 'A', '95', '4.0', 'Exceeds'
            $table->string('display_value', 50);
            $table->decimal('numeric_value', 5, 2);
            $table->decimal('gpa_points', 3, 2)->nullable();

            // Range Definition
            $table->decimal('percentage_min', 5, 2)->nullable();
            $table->decimal('percentage_max', 5, 2)->nullable();

            // Metadata
            $table->text('description')->nullable();
            $table->string('color_code', 7)->nullable(); // Hex color
            $table->boolean('is_passing')->default(true);
            $table->integer('sort_order');

            $table->timestamps();

            // Indexes
            $table->index(['grade_scale_id']);
            $table->index(['sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_levels');
    }
};
EOF

# Create Grade Entries migration with partitioning
cat > database/migrations/2024_01_06_000001_create_grade_entries_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('academic_term_id')->constrained('academic_terms')->onDelete('cascade');

            // Assessment Information
            $table->string('assessment_name', 255);
            $table->enum('assessment_type', [
                'formative', 'summative', 'project', 'participation', 'homework', 'quiz', 'exam'
            ]);
            $table->date('assessment_date');

            // Grade Information
            $table->decimal('raw_score', 6, 2)->nullable();
            $table->decimal('percentage_score', 5, 2)->nullable();
            $table->string('letter_grade', 5)->nullable();
            $table->decimal('points_earned', 6, 2)->nullable();
            $table->decimal('points_possible', 6, 2)->nullable();

            // Weighting & Categories
            $table->string('grade_category', 100)->nullable();
            $table->decimal('weight', 5, 2)->default(1.00);

            // Tracking & Audit
            $table->foreignId('entered_by')->constrained('teachers');
            $table->timestamp('entered_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->foreignId('modified_by')->nullable()->constrained('teachers');
            $table->timestamp('modified_at')->nullable();

            // Comments & Notes
            $table->text('teacher_comments')->nullable();
            $table->text('private_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['student_id', 'academic_term_id']);
            $table->index(['class_id', 'assessment_date']);
            $table->index(['school_id', 'academic_term_id']);
            $table->index(['entered_by', 'entered_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_entries');
    }
};
EOF

echo "✅ Academic Management Migrations created successfully!"
echo "📁 Migrations created in: database/migrations/"
echo "📋 Created migrations:"
echo "   - subjects table"
echo "   - classes table"
echo "   - student_class_enrollments table"
echo "   - teachers table"
echo "   - grading_systems table"
echo "   - grade_scales table"
echo "   - grade_levels table"
echo "   - grade_entries table"
echo "🚀 Run: php artisan migrate"
echo "🔧 Next: Create controllers and services"
