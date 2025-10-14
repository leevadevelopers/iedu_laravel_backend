<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to modify the enum to add new values
        DB::statement("ALTER TABLE form_templates MODIFY COLUMN category ENUM(
            'school_registration', 'school_enrollment', 'school_setup',
            'student_enrollment', 'student_registration', 'student_transfer',
            'attendance', 'grades', 'academic_records', 'behavior_incident',
            'parent_communication', 'teacher_evaluation', 'curriculum_planning',
            'extracurricular', 'field_trip', 'parent_meeting', 'student_health',
            'special_education', 'discipline', 'graduation', 'scholarship',
            'staff_management', 'faculty_recruitment', 'professional_development',
            'school_calendar', 'events_management', 'facilities_management',
            'transportation', 'cafeteria_management', 'library_management',
            'technology_management', 'security_management', 'maintenance_requests',
            'financial_aid', 'tuition_management', 'donation_management',
            'alumni_relations', 'community_outreach', 'partnership_management',
            'document_upload', 'academic_year_setup', 'academic_term_setup'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the added categories
        // Keep enum as superset to avoid data truncation on rollback
        DB::statement("ALTER TABLE form_templates MODIFY COLUMN category ENUM(
            'school_registration', 'school_enrollment', 'school_setup',
            'student_enrollment', 'student_registration', 'student_transfer',
            'attendance', 'grades', 'academic_records', 'behavior_incident',
            'parent_communication', 'teacher_evaluation', 'curriculum_planning',
            'extracurricular', 'field_trip', 'parent_meeting', 'student_health',
            'special_education', 'discipline', 'graduation', 'scholarship',
            'staff_management', 'faculty_recruitment', 'professional_development',
            'school_calendar', 'events_management', 'facilities_management',
            'transportation', 'cafeteria_management', 'library_management',
            'technology_management', 'security_management', 'maintenance_requests',
            'financial_aid', 'tuition_management', 'donation_management',
            'alumni_relations', 'community_outreach', 'partnership_management',
            'document_upload', 'academic_year_setup', 'academic_term_setup'
        )");
    }
};
