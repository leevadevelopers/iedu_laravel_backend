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
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');


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
            $table->enum('status', ['draft', 'active', 'inactive', 'terminated', 'on_leave', 'archived'])->default('active');

            // Educational Background
            $table->json('education_json')->nullable(); // Degrees, certifications
            $table->json('certifications_json')->nullable(); // Teaching licenses, endorsements
            $table->json('specializations_json')->nullable(); // Subject areas, grade levels

            // Professional Details
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            // schedule_json removed - use Schedule model instead

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
            $table->index(['tenant_id']);
            $table->index(['school_id', 'tenant_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('teachers');
    }
};
