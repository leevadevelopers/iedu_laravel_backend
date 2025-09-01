<?php

namespace App\Services\Forms\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EducationalValidationRules
{
    /**
     * Get validation rules for educational forms
     */
    public static function getRules(string $category, array $context = []): array
    {
        $baseRules = self::getBaseRules();
        $categoryRules = self::getCategorySpecificRules($category, $context);

        return array_merge($baseRules, $categoryRules);
    }

    /**
     * Base validation rules for all educational forms
     */
    private static function getBaseRules(): array
    {
        return [
            'student_id' => 'nullable|string|max:50',
            'academic_year' => 'required|string|max:9',
            'semester' => 'nullable|string|in:1,2,3,4',
            'grade_level' => 'nullable|string|max:20',
            'school_code' => 'required|string|max:20',
            'teacher_id' => 'nullable|string|max:50',
            'parent_contact' => 'nullable|string|max:20',
            'emergency_contact' => 'nullable|string|max:20',
        ];
    }

    /**
     * Category-specific validation rules
     */
    private static function getCategorySpecificRules(string $category, array $context = []): array
    {
        switch ($category) {
            case 'school_registration':
                return self::getSchoolRegistrationRules();

            case 'school_enrollment':
                return self::getSchoolEnrollmentRules();

            case 'school_setup':
                return self::getSchoolSetupRules();

            case 'student_enrollment':
                return self::getEnrollmentRules();

            case 'student_registration':
                return self::getRegistrationRules();

            case 'attendance':
                return self::getAttendanceRules();

            case 'grades':
                return self::getGradesRules();

            case 'academic_records':
                return self::getAcademicRecordsRules();

            case 'behavior_incident':
                return self::getBehaviorIncidentRules();

            case 'parent_communication':
                return self::getParentCommunicationRules();

            case 'teacher_evaluation':
                return self::getTeacherEvaluationRules();

            case 'curriculum_planning':
                return self::getCurriculumPlanningRules();

            case 'extracurricular':
                return self::getExtracurricularRules();

            case 'field_trip':
                return self::getFieldTripRules();

            case 'parent_meeting':
                return self::getParentMeetingRules();

            case 'student_health':
                return self::getStudentHealthRules();

            case 'special_education':
                return self::getSpecialEducationRules();

            case 'discipline':
                return self::getDisciplineRules();

            case 'graduation':
                return self::getGraduationRules();

            case 'scholarship':
                return self::getScholarshipRules();

            case 'staff_management':
                return self::getStaffManagementRules();

            case 'faculty_recruitment':
                return self::getFacultyRecruitmentRules();

            case 'professional_development':
                return self::getProfessionalDevelopmentRules();

            case 'school_calendar':
                return self::getSchoolCalendarRules();

            case 'events_management':
                return self::getEventsManagementRules();

            case 'facilities_management':
                return self::getFacilitiesManagementRules();

            case 'transportation':
                return self::getTransportationRules();

            case 'cafeteria_management':
                return self::getCafeteriaManagementRules();

            case 'library_management':
                return self::getLibraryManagementRules();

            case 'technology_management':
                return self::getTechnologyManagementRules();

            case 'security_management':
                return self::getSecurityManagementRules();

            case 'maintenance_requests':
                return self::getMaintenanceRequestsRules();

            case 'financial_aid':
                return self::getFinancialAidRules();

            case 'tuition_management':
                return self::getTuitionManagementRules();

            case 'donation_management':
                return self::getDonationManagementRules();

            case 'alumni_relations':
                return self::getAlumniRelationsRules();

            case 'community_outreach':
                return self::getCommunityOutreachRules();

            case 'partnership_management':
                return self::getPartnershipManagementRules();

            default:
                return [];
        }
    }

    /**
     * Student enrollment validation rules
     */
    private static function getEnrollmentRules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'nationality' => 'required|string|max:50',
            'place_of_birth' => 'required|string|max:100',
            'previous_school' => 'nullable|string|max:200',
            'previous_grade' => 'nullable|string|max:20',
            'enrollment_date' => 'required|date|after_or_equal:today',
            'parent_guardian_name' => 'required|string|max:200',
            'parent_guardian_relationship' => 'required|string|max:50',
            'parent_guardian_phone' => 'required|string|max:20',
            'parent_guardian_email' => 'nullable|email|max:100',
            'parent_guardian_address' => 'required|string|max:500',
            'emergency_contact_name' => 'required|string|max:200',
            'emergency_contact_phone' => 'required|string|max:20',
            'emergency_contact_relationship' => 'required|string|max:50',
            'medical_conditions' => 'nullable|string|max:1000',
            'allergies' => 'nullable|string|max:500',
            'medications' => 'nullable|string|max:500',
            'special_needs' => 'nullable|string|max:1000',
            'documents_required' => 'required|array|min:1',
            'documents_required.*' => 'string|in:birth_certificate,id_card,previous_school_records,medical_records,immunization_records',
        ];
    }

    /**
     * Student registration validation rules
     */
    private static function getRegistrationRules(): array
    {
        return [
            'registration_type' => 'required|in:new,returning,transfer',
            'academic_program' => 'required|string|max:100',
            'class_section' => 'nullable|string|max:20',
            'homeroom_teacher' => 'nullable|string|max:100',
            'transportation_required' => 'boolean',
            'transportation_type' => 'nullable|required_if:transportation_required,true|string|max:50',
            'lunch_program' => 'boolean',
            'after_school_program' => 'boolean',
            'parent_volunteer' => 'boolean',
            'communication_preferences' => 'array',
            'communication_preferences.*' => 'string|in:email,sms,phone,mail,app',
        ];
    }

    /**
     * Attendance validation rules
     */
    private static function getAttendanceRules(): array
    {
        return [
            'attendance_date' => 'required|date|before_or_equal:today',
            'attendance_status' => 'required|in:present,absent,late,excused,early_dismissal',
            'arrival_time' => 'nullable|date_format:H:i',
            'departure_time' => 'nullable|date_format:H:i',
            'absence_reason' => 'nullable|required_if:attendance_status,absent,excused|string|max:500',
            'doctor_note' => 'nullable|boolean',
            'parent_note' => 'nullable|string|max:500',
            'makeup_work_assigned' => 'nullable|boolean',
            'makeup_work_completed' => 'nullable|boolean',
        ];
    }

    /**
     * Grades validation rules
     */
    private static function getGradesRules(): array
    {
        return [
            'subject' => 'required|string|max:100',
            'assignment_type' => 'required|string|max:50',
            'assignment_name' => 'required|string|max:200',
            'grade_value' => 'required|numeric|min:0|max:100',
            'grade_scale' => 'nullable|string|max:20',
            'weight' => 'nullable|numeric|min:0|max:100',
            'comments' => 'nullable|string|max:1000',
            'graded_by' => 'required|string|max:100',
            'graded_at' => 'required|date|before_or_equal:today',
            'late_submission' => 'boolean',
            'extra_credit' => 'boolean',
        ];
    }

    /**
     * Academic records validation rules
     */
    private static function getAcademicRecordsRules(): array
    {
        return [
            'record_type' => 'required|string|max:50',
            'academic_period' => 'required|string|max:20',
            'gpa' => 'nullable|numeric|min:0|max:4',
            'class_rank' => 'nullable|integer|min:1',
            'total_students' => 'nullable|integer|min:1',
            'honors_awards' => 'nullable|array',
            'honors_awards.*' => 'string|max:200',
            'academic_warnings' => 'nullable|array',
            'academic_warnings.*' => 'string|max:500',
            'improvement_plan' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Behavior incident validation rules
     */
    private static function getBehaviorIncidentRules(): array
    {
        return [
            'incident_date' => 'required|date|before_or_equal:today',
            'incident_time' => 'required|date_format:H:i',
            'incident_location' => 'required|string|max:200',
            'incident_type' => 'required|string|max:100',
            'incident_description' => 'required|string|max:2000',
            'witnesses' => 'nullable|array',
            'witnesses.*' => 'string|max:100',
            'reported_by' => 'required|string|max:100',
            'severity_level' => 'required|in:minor,moderate,major,severe',
            'disciplinary_action' => 'nullable|string|max:500',
            'parent_notified' => 'boolean',
            'parent_notification_date' => 'nullable|date|after_or_equal:incident_date',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date|after:incident_date',
        ];
    }

    /**
     * Parent communication validation rules
     */
    private static function getParentCommunicationRules(): array
    {
        return [
            'communication_type' => 'required|string|max:50',
            'communication_method' => 'required|string|max:50',
            'subject' => 'required|string|max:200',
            'message_content' => 'required|string|max:2000',
            'initiated_by' => 'required|string|max:100',
            'parent_response' => 'nullable|string|max:1000',
            'response_date' => 'nullable|date|after:created_at',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date|after:created_at',
            'communication_status' => 'required|in:sent,delivered,read,responded,closed',
        ];
    }

    /**
     * Teacher evaluation validation rules
     */
    private static function getTeacherEvaluationRules(): array
    {
        return [
            'evaluation_period' => 'required|string|max:20',
            'evaluator' => 'required|string|max:100',
            'evaluation_type' => 'required|string|max:50',
            'teaching_effectiveness' => 'required|integer|min:1|max:5',
            'classroom_management' => 'required|integer|min:1|max:5',
            'lesson_planning' => 'required|integer|min:1|max:5',
            'student_engagement' => 'required|integer|min:1|max:5',
            'assessment_practices' => 'required|integer|min:1|max:5',
            'professional_development' => 'required|integer|min:1|max:5',
            'strengths' => 'required|string|max:1000',
            'areas_for_improvement' => 'required|string|max:1000',
            'recommendations' => 'required|string|max:1000',
            'overall_rating' => 'required|integer|min:1|max:5',
        ];
    }

    /**
     * Curriculum planning validation rules
     */
    private static function getCurriculumPlanningRules(): array
    {
        return [
            'subject_area' => 'required|string|max:100',
            'grade_level' => 'required|string|max:20',
            'unit_title' => 'required|string|max:200',
            'learning_objectives' => 'required|array|min:1',
            'learning_objectives.*' => 'string|max:500',
            'essential_questions' => 'required|array|min:1',
            'essential_questions.*' => 'string|max:300',
            'standards_alignment' => 'required|array|min:1',
            'standards_alignment.*' => 'string|max:200',
            'assessment_strategies' => 'required|array|min:1',
            'assessment_strategies.*' => 'string|max:300',
            'resources_materials' => 'nullable|array',
            'resources_materials.*' => 'string|max:300',
            'estimated_duration' => 'required|string|max:50',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'string|max:200',
        ];
    }

    /**
     * Extracurricular activities validation rules
     */
    private static function getExtracurricularRules(): array
    {
        return [
            'activity_name' => 'required|string|max:200',
            'activity_type' => 'required|string|max:100',
            'supervisor' => 'required|string|max:100',
            'meeting_schedule' => 'required|string|max:200',
            'meeting_location' => 'required|string|max:200',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'maximum_participants' => 'nullable|integer|min:1',
            'current_participants' => 'nullable|integer|min:0',
            'eligibility_requirements' => 'nullable|string|max:1000',
            'cost' => 'nullable|numeric|min:0',
            'equipment_needed' => 'nullable|array',
            'equipment_needed.*' => 'string|max:200',
            'parent_consent_required' => 'boolean',
            'medical_clearance_required' => 'boolean',
        ];
    }

    /**
     * Field trip validation rules
     */
    private static function getFieldTripRules(): array
    {
        return [
            'trip_name' => 'required|string|max:200',
            'destination' => 'required|string|max:200',
            'trip_date' => 'required|date|after:today',
            'departure_time' => 'required|date_format:H:i',
            'return_time' => 'required|date_format:H:i',
            'transportation_method' => 'required|string|max:100',
            'chaperones' => 'required|array|min:1',
            'chaperones.*' => 'string|max:100',
            'maximum_students' => 'required|integer|min:1',
            'cost_per_student' => 'nullable|numeric|min:0',
            'educational_objectives' => 'required|string|max:1000',
            'safety_considerations' => 'required|string|max:1000',
            'emergency_contact' => 'required|string|max:100',
            'emergency_phone' => 'required|string|max:20',
            'parent_consent_required' => 'boolean',
            'medical_information_required' => 'boolean',
            'special_equipment_needed' => 'nullable|array',
            'special_equipment_needed.*' => 'string|max:200',
        ];
    }

    /**
     * Parent meeting validation rules
     */
    private static function getParentMeetingRules(): array
    {
        return [
            'meeting_type' => 'required|string|max:100',
            'meeting_date' => 'required|date|after:today',
            'meeting_time' => 'required|date_format:H:i',
            'meeting_duration' => 'required|integer|min:15|max:480',
            'meeting_location' => 'required|string|max:200',
            'participants' => 'required|array|min:1',
            'participants.*' => 'string|max:100',
            'agenda_items' => 'required|array|min:1',
            'agenda_items.*' => 'string|max:300',
            'materials_needed' => 'nullable|array',
            'materials_needed.*' => 'string|max:200',
            'follow_up_actions' => 'nullable|array',
            'follow_up_actions.*' => 'string|max:300',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Student health validation rules
     */
    private static function getStudentHealthRules(): array
    {
        return [
            'health_issue_type' => 'required|string|max:100',
            'symptoms' => 'required|string|max:1000',
            'onset_date' => 'required|date|before_or_equal:today',
            'severity' => 'required|in:mild,moderate,severe',
            'current_medications' => 'nullable|array',
            'current_medications.*' => 'string|max:200',
            'allergies' => 'nullable|array',
            'allergies.*' => 'string|max:200',
            'family_history' => 'nullable|string|max:1000',
            'doctor_consultation' => 'boolean',
            'doctor_name' => 'nullable|string|max:100',
            'doctor_phone' => 'nullable|string|max:20',
            'treatment_plan' => 'nullable|string|max:1000',
            'return_to_school_date' => 'nullable|date|after:today',
            'restrictions' => 'nullable|string|max:500',
            'emergency_contact' => 'required|string|max:100',
            'emergency_phone' => 'required|string|max:20',
        ];
    }

    /**
     * Special education validation rules
     */
    private static function getSpecialEducationRules(): array
    {
        return [
            'disability_category' => 'required|string|max:100',
            'diagnosis_date' => 'required|date|before_or_equal:today',
            'diagnosing_professional' => 'required|string|max:100',
            'professional_credentials' => 'required|string|max:100',
            'assessment_results' => 'required|string|max:2000',
            'iep_goals' => 'required|array|min:1',
            'iep_goals.*' => 'string|max:500',
            'accommodations' => 'required|array|min:1',
            'accommodations.*' => 'string|max:300',
            'modifications' => 'nullable|array',
            'modifications.*' => 'string|max:300',
            'related_services' => 'nullable|array',
            'related_services.*' => 'string|max:200',
            'placement_setting' => 'required|string|max:100',
            'progress_monitoring' => 'required|string|max:500',
            'parent_consent' => 'required|boolean',
            'review_date' => 'required|date|after:today',
        ];
    }

    /**
     * Discipline validation rules
     */
    private static function getDisciplineRules(): array
    {
        return [
            'incident_date' => 'required|date|before_or_equal:today',
            'incident_time' => 'required|date_format:H:i',
            'incident_location' => 'required|string|max:200',
            'rule_violation' => 'required|string|max:200',
            'incident_description' => 'required|string|max:2000',
            'witnesses' => 'nullable|array',
            'witnesses.*' => 'string|max:100',
            'reported_by' => 'required|string|max:100',
            'severity_level' => 'required|in:minor,moderate,major,severe',
            'previous_incidents' => 'nullable|integer|min:0',
            'disciplinary_action' => 'required|string|max:500',
            'action_duration' => 'nullable|string|max:100',
            'parent_notified' => 'boolean',
            'parent_notification_date' => 'nullable|date|after_or_equal:incident_date',
            'appeal_filed' => 'boolean',
            'appeal_date' => 'nullable|date|after:incident_date',
            'appeal_status' => 'nullable|string|max:50',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date|after:incident_date',
        ];
    }

    /**
     * Graduation validation rules
     */
    private static function getGraduationRules(): array
    {
        return [
            'graduation_date' => 'required|date|after:today',
            'graduation_ceremony' => 'boolean',
            'ceremony_location' => 'nullable|string|max:200',
            'ceremony_time' => 'nullable|date_format:H:i',
            'graduation_requirements_met' => 'required|boolean',
            'gpa' => 'required|numeric|min:0|max:4',
            'credits_earned' => 'required|integer|min:1',
            'required_credits' => 'required|integer|min:1',
            'honors_awards' => 'nullable|array',
            'honors_awards.*' => 'string|max:200',
            'scholarships_awarded' => 'nullable|array',
            'scholarships_awarded.*' => 'string|max:200',
            'college_acceptances' => 'nullable|array',
            'college_acceptances.*' => 'string|max:200',
            'career_plans' => 'nullable|string|max:1000',
            'graduation_speech' => 'boolean',
            'speech_topic' => 'nullable|string|max:200',
            'parent_attendance' => 'boolean',
            'guest_tickets' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Scholarship validation rules
     */
    private static function getScholarshipRules(): array
    {
        return [
            'scholarship_name' => 'required|string|max:200',
            'scholarship_type' => 'required|string|max:100',
            'sponsoring_organization' => 'required|string|max:200',
            'application_deadline' => 'required|date|after:today',
            'amount' => 'required|numeric|min:0',
            'renewable' => 'boolean',
            'renewal_requirements' => 'nullable|string|max:1000',
            'eligibility_criteria' => 'required|string|max:1000',
            'required_documents' => 'required|array|min:1',
            'required_documents.*' => 'string|max:200',
            'application_status' => 'required|in:draft,submitted,under_review,approved,rejected',
            'submission_date' => 'nullable|date|before_or_equal:today',
            'review_date' => 'nullable|date|after:submission_date',
            'decision_date' => 'nullable|date|after:review_date',
            'decision_notes' => 'nullable|string|max:1000',
            'acceptance_deadline' => 'nullable|date|after:decision_date',
            'acceptance_confirmed' => 'boolean',
            'funds_disbursed' => 'boolean',
            'disbursement_date' => 'nullable|date|after:acceptance_confirmed',
        ];
    }

    /**
     * Validate form data against educational rules
     */
    public static function validateFormData(array $formData, string $category, array $context = []): array
    {
        $rules = self::getRules($category, $context);

        $validator = Validator::make($formData, $rules);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
                'warnings' => []
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'warnings' => self::generateWarnings($formData, $category)
        ];
    }

    /**
     * Generate warnings for potential issues
     */
    private static function generateWarnings(array $formData, string $category): array
    {
        $warnings = [];

        // Add category-specific warnings
        switch ($category) {
            case 'student_enrollment':
                if (isset($formData['medical_conditions']) && !empty($formData['medical_conditions'])) {
                    $warnings[] = 'Medical conditions documented. Ensure health office is notified.';
                }
                if (isset($formData['special_needs']) && !empty($formData['special_needs'])) {
                    $warnings[] = 'Special needs identified. Schedule IEP meeting if required.';
                }
                break;

            case 'attendance':
                if (isset($formData['attendance_status']) && $formData['attendance_status'] === 'absent') {
                    $warnings[] = 'Student absent. Follow up with parent/guardian required.';
                }
                break;

            case 'grades':
                if (isset($formData['grade_value']) && $formData['grade_value'] < 60) {
                    $warnings[] = 'Low grade recorded. Consider academic intervention.';
                }
                break;

            case 'behavior_incident':
                if (isset($formData['severity_level']) && in_array($formData['severity_level'], ['major', 'severe'])) {
                    $warnings[] = 'Serious incident reported. Immediate administrative review required.';
                }
                break;
        }

        return $warnings;
    }

    /**
     * School registration validation rules
     */
    private static function getSchoolRegistrationRules(): array
    {
        return [
            'school_name' => 'required|string|max:200',
            'school_type' => 'required|in:public,private,charter,magnet',
            'registration_date' => 'required|date|after_or_equal:today',
            'school_address' => 'required|string|max:500',
            'contact_person' => 'required|string|max:200',
            'contact_phone' => 'required|string|max:20',
            'contact_email' => 'required|email|max:100',
            'accreditation_status' => 'required|in:accredited,provisional,not_accredited',
            'accreditation_expiry' => 'nullable|date|after:today',
            'capacity' => 'required|integer|min:1',
            'current_enrollment' => 'required|integer|min:0',
            'registration_fee' => 'nullable|numeric|min:0',
            'documents_required' => 'required|array|min:1',
            'documents_required.*' => 'string|in:license,accreditation,insurance,financial_statements'
        ];
    }

    /**
     * School enrollment validation rules
     */
    private static function getSchoolEnrollmentRules(): array
    {
        return [
            'enrollment_period' => 'required|string|max:100',
            'enrollment_start_date' => 'required|date|before_or_equal:enrollment_end_date',
            'enrollment_end_date' => 'required|date|after_or_equal:enrollment_start_date',
            'enrollment_capacity' => 'required|integer|min:1',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'enrollment_requirements' => 'required|array|min:1',
            'enrollment_requirements.*' => 'string|max:200',
            'enrollment_status' => 'required|in:open,waitlist,closed',
            'priority_enrollment' => 'boolean',
            'priority_criteria' => 'nullable|string|max:500'
        ];
    }

    /**
     * School setup validation rules
     */
    private static function getSchoolSetupRules(): array
    {
        return [
            'setup_phase' => 'required|in:planning,construction,equipment,staffing,testing,operational',
            'target_opening_date' => 'required|date|after:today',
            'setup_budget' => 'required|numeric|min:0',
            'setup_timeline' => 'required|array|min:1',
            'setup_timeline.*.phase' => 'required|string|max:100',
            'setup_timeline.*.start_date' => 'required|date',
            'setup_timeline.*.end_date' => 'required|date|after:setup_timeline.*.start_date',
            'setup_timeline.*.status' => 'required|in:not_started,in_progress,completed,delayed',
            'required_permits' => 'required|array|min:1',
            'required_permits.*' => 'string|max:200',
            'permits_status' => 'required|array',
            'permits_status.*.permit_type' => 'required|string|max:200',
            'permits_status.*.status' => 'required|in:not_applied,applied,approved,rejected'
        ];
    }

    /**
     * Staff management validation rules
     */
    private static function getStaffManagementRules(): array
    {
        return [
            'staff_id' => 'required|string|max:50',
            'staff_type' => 'required|in:teacher,administrator,support_staff,maintenance,security',
            'department' => 'required|string|max:100',
            'position' => 'required|string|max:100',
            'hire_date' => 'required|date|before_or_equal:today',
            'employment_status' => 'required|in:full_time,part_time,contract,temporary',
            'salary_grade' => 'required|string|max:20',
            'supervisor' => 'nullable|string|max:200',
            'performance_rating' => 'nullable|numeric|min:1|max:5',
            'training_required' => 'nullable|array',
            'training_required.*' => 'string|max:200'
        ];
    }

    /**
     * Faculty recruitment validation rules
     */
    private static function getFacultyRecruitmentRules(): array
    {
        return [
            'position_title' => 'required|string|max:200',
            'department' => 'required|string|max:100',
            'position_type' => 'required|in:full_time,part_time,adjunct,visiting',
            'qualifications_required' => 'required|array|min:1',
            'qualifications_required.*' => 'string|max:200',
            'experience_required' => 'required|integer|min:0',
            'education_required' => 'required|string|max:100',
            'salary_range_min' => 'required|numeric|min:0',
            'salary_range_max' => 'required|numeric|min:salary_range_min',
            'application_deadline' => 'required|date|after:today',
            'interview_process' => 'required|array|min:1',
            'interview_process.*.stage' => 'required|string|max:100',
            'interview_process.*.duration' => 'required|integer|min:1'
        ];
    }

    /**
     * Professional development validation rules
     */
    private static function getProfessionalDevelopmentRules(): array
    {
        return [
            'program_name' => 'required|string|max:200',
            'program_type' => 'required|in:workshop,conference,certification,degree,online_course',
            'provider' => 'required|string|max:200',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'duration_hours' => 'required|integer|min:1',
            'cost' => 'nullable|numeric|min:0',
            'funding_source' => 'nullable|string|max:100',
            'target_audience' => 'required|array|min:1',
            'target_audience.*' => 'string|max:100',
            'learning_objectives' => 'required|array|min:1',
            'learning_objectives.*' => 'string|max:500',
            'certification_offered' => 'boolean',
            'certification_name' => 'nullable|string|max:200'
        ];
    }

    /**
     * School calendar validation rules
     */
    private static function getSchoolCalendarRules(): array
    {
        return [
            'academic_year' => 'required|string|max:20',
            'semester' => 'required|in:fall,spring,summer,full_year',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'required|string|max:100',
            'holidays.*.date' => 'required|date',
            'holidays.*.type' => 'required|in:public_holiday,school_holiday,professional_development',
            'exam_periods' => 'nullable|array',
            'exam_periods.*.name' => 'required|string|max:100',
            'exam_periods.*.start_date' => 'required|date',
            'exam_periods.*.end_date' => 'required|date|after:exam_periods.*.start_date',
            'parent_teacher_conferences' => 'nullable|array',
            'parent_teacher_conferences.*.date' => 'required|date',
            'parent_teacher_conferences.*.duration_minutes' => 'required|integer|min:15'
        ];
    }

    /**
     * Events management validation rules
     */
    private static function getEventsManagementRules(): array
    {
        return [
            'event_name' => 'required|string|max:200',
            'event_type' => 'required|in:academic,athletic,arts,cultural,community,celebration',
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'required|string|max:200',
            'capacity' => 'nullable|integer|min:1',
            'registration_required' => 'boolean',
            'registration_deadline' => 'nullable|date|before:event_date',
            'cost' => 'nullable|numeric|min:0',
            'organizer' => 'required|string|max:200',
            'contact_person' => 'required|string|max:200',
            'contact_phone' => 'required|string|max:20',
            'contact_email' => 'required|email|max:100',
            'description' => 'required|string|max:1000',
            'target_audience' => 'required|array|min:1',
            'target_audience.*' => 'string|max:100'
        ];
    }

    /**
     * Facilities management validation rules
     */
    private static function getFacilitiesManagementRules(): array
    {
        return [
            'facility_name' => 'required|string|max:200',
            'facility_type' => 'required|in:classroom,library,laboratory,gymnasium,auditorium,cafeteria,office,maintenance',
            'building' => 'required|string|max:100',
            'floor' => 'nullable|string|max:20',
            'room_number' => 'nullable|string|max:20',
            'capacity' => 'nullable|integer|min:1',
            'square_footage' => 'nullable|numeric|min:0',
            'equipment_included' => 'nullable|array',
            'equipment_included.*' => 'string|max:200',
            'maintenance_schedule' => 'nullable|string|max:100',
            'last_maintenance_date' => 'nullable|date|before_or_equal:today',
            'next_maintenance_date' => 'nullable|date|after:last_maintenance_date',
            'access_restrictions' => 'nullable|string|max:500',
            'booking_required' => 'boolean',
            'booking_contact' => 'nullable|string|max:200'
        ];
    }

    /**
     * Transportation validation rules
     */
    private static function getTransportationRules(): array
    {
        return [
            'route_number' => 'required|string|max:20',
            'route_name' => 'required|string|max:200',
            'vehicle_type' => 'required|in:bus,van,car,walking',
            'vehicle_capacity' => 'required|integer|min:1',
            'driver_name' => 'required|string|max:200',
            'driver_license' => 'required|string|max:50',
            'driver_phone' => 'required|string|max:20',
            'pickup_time' => 'required|date_format:H:i',
            'dropoff_time' => 'required|date_format:H:i|after:pickup_time',
            'pickup_location' => 'required|string|max:200',
            'dropoff_location' => 'required|string|max:200',
            'stops' => 'required|array|min:1',
            'stops.*.location' => 'required|string|max:200',
            'stops.*.time' => 'required|date_format:H:i',
            'stops.*.type' => 'required|in:pickup,dropoff,both',
            'route_distance' => 'nullable|numeric|min:0',
            'estimated_duration' => 'nullable|integer|min:1'
        ];
    }

    /**
     * Cafeteria management validation rules
     */
    private static function getCafeteriaManagementRules(): array
    {
        return [
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'meal_date' => 'required|date|after_or_equal:today',
            'menu_items' => 'required|array|min:1',
            'menu_items.*.name' => 'required|string|max:200',
            'menu_items.*.category' => 'required|in:main_dish,side_dish,salad,dessert,beverage',
            'menu_items.*.dietary_restrictions' => 'nullable|array',
            'menu_items.*.dietary_restrictions.*' => 'string|in:vegetarian,vegan,gluten_free,dairy_free,nut_free',
            'nutritional_info' => 'nullable|array',
            'nutritional_info.calories' => 'nullable|integer|min:0',
            'nutritional_info.protein' => 'nullable|numeric|min:0',
            'nutritional_info.carbohydrates' => 'nullable|numeric|min:0',
            'nutritional_info.fat' => 'nullable|numeric|min:0',
            'allergen_info' => 'nullable|array',
            'allergen_info.*' => 'string|in:peanuts,tree_nuts,milk,eggs,soy,wheat,fish,shellfish',
            'cost' => 'nullable|numeric|min:0',
            'serving_time_start' => 'required|date_format:H:i',
            'serving_time_end' => 'required|date_format:H:i|after:serving_time_start'
        ];
    }

    /**
     * Library management validation rules
     */
    private static function getLibraryManagementRules(): array
    {
        return [
            'book_title' => 'required|string|max:200',
            'author' => 'required|string|max:200',
            'isbn' => 'nullable|string|max:20',
            'category' => 'required|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'reading_level' => 'nullable|string|max:50',
            'publication_year' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'publisher' => 'nullable|string|max:200',
            'copies_available' => 'required|integer|min:0',
            'total_copies' => 'required|integer|min:copies_available',
            'location' => 'required|string|max:100',
            'shelf_number' => 'nullable|string|max:20',
            'condition' => 'required|in:excellent,good,fair,poor,damaged',
            'last_checkout_date' => 'nullable|date|before_or_equal:today',
            'due_date' => 'nullable|date|after:last_checkout_date',
            'checkout_duration_days' => 'nullable|integer|min:1|max:30',
            'renewal_allowed' => 'boolean',
            'max_renewals' => 'nullable|integer|min:0'
        ];
    }

    /**
     * Technology management validation rules
     */
    private static function getTechnologyManagementRules(): array
    {
        return [
            'device_type' => 'required|in:computer,tablet,projector,printer,network_device,software,other',
            'device_name' => 'required|string|max:200',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'location' => 'required|string|max:200',
            'assigned_to' => 'nullable|string|max:200',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            'last_maintenance_date' => 'nullable|date|before_or_equal:today',
            'next_maintenance_date' => 'nullable|date|after:last_maintenance_date',
            'status' => 'required|in:operational,maintenance,repair,retired,stolen',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|max:17',
            'software_installed' => 'nullable|array',
            'software_installed.*' => 'string|max:200',
            'access_restrictions' => 'nullable|string|max:500',
            'backup_schedule' => 'nullable|string|max:100'
        ];
    }

    /**
     * Security management validation rules
     */
    private static function getSecurityManagementRules(): array
    {
        return [
            'incident_type' => 'required|in:theft,vandalism,unauthorized_access,harassment,other',
            'incident_date' => 'required|date|before_or_equal:today',
            'incident_time' => 'required|date_format:H:i',
            'location' => 'required|string|max:200',
            'reported_by' => 'required|string|max:200',
            'witnesses' => 'nullable|array',
            'witnesses.*' => 'string|max:200',
            'description' => 'required|string|max:1000',
            'severity_level' => 'required|in:low,medium,high,critical',
            'police_notified' => 'boolean',
            'police_report_number' => 'nullable|string|max:50',
            'security_cameras' => 'nullable|boolean',
            'camera_footage_reviewed' => 'nullable|boolean',
            'actions_taken' => 'required|array|min:1',
            'actions_taken.*' => 'string|max:500',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date|after:incident_date',
            'preventive_measures' => 'nullable|array',
            'preventive_measures.*' => 'string|max:500'
        ];
    }

    /**
     * Maintenance requests validation rules
     */
    private static function getMaintenanceRequestsRules(): array
    {
        return [
            'request_type' => 'required|in:repair,preventive_maintenance,inspection,installation,cleaning,other',
            'priority_level' => 'required|in:low,medium,high,urgent,emergency',
            'location' => 'required|string|max:200',
            'description' => 'required|string|max:1000',
            'reported_by' => 'required|string|max:200',
            'reported_date' => 'required|date|before_or_equal:today',
            'requested_completion_date' => 'nullable|date|after:reported_date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'approved_budget' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|string|max:200',
            'status' => 'required|in:submitted,approved,in_progress,completed,cancelled',
            'completion_date' => 'nullable|date|after:reported_date',
            'actual_cost' => 'nullable|numeric|min:0',
            'work_notes' => 'nullable|string|max:1000',
            'photos_attached' => 'boolean',
            'follow_up_required' => 'boolean'
        ];
    }

    /**
     * Financial aid validation rules
     */
    private static function getFinancialAidRules(): array
    {
        return [
            'aid_type' => 'required|in:scholarship,grant,loan,work_study,tuition_waiver',
            'aid_name' => 'required|string|max:200',
            'provider' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0',
            'application_deadline' => 'required|date|after:today',
            'eligibility_criteria' => 'required|string|max:1000',
            'required_documents' => 'required|array|min:1',
            'required_documents.*' => 'string|max:200',
            'application_status' => 'required|in:draft,submitted,under_review,approved,rejected',
            'submission_date' => 'nullable|date|before_or_equal:today',
            'review_date' => 'nullable|date|after:submission_date',
            'decision_date' => 'nullable|date|after:review_date',
            'disbursement_date' => 'nullable|date|after:decision_date',
            'renewal_required' => 'boolean',
            'renewal_deadline' => 'nullable|date|after:disbursement_date',
            'academic_requirements' => 'nullable|array',
            'academic_requirements.*' => 'string|max:200'
        ];
    }

    /**
     * Tuition management validation rules
     */
    private static function getTuitionManagementRules(): array
    {
        return [
            'student_id' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'semester' => 'required|in:fall,spring,summer,full_year',
            'tuition_amount' => 'required|numeric|min:0',
            'fees_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'payment_plan' => 'nullable|string|max:100',
            'payment_due_date' => 'required|date|after:today',
            'payment_status' => 'required|in:unpaid,partial,paid,overdue,waived',
            'amount_paid' => 'nullable|numeric|min:0',
            'amount_due' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date|before_or_equal:today',
            'late_fees' => 'nullable|numeric|min:0',
            'financial_aid_applied' => 'nullable|numeric|min:0',
            'balance' => 'required|numeric',
            'notes' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Donation management validation rules
     */
    private static function getDonationManagementRules(): array
    {
        return [
            'donor_name' => 'required|string|max:200',
            'donor_type' => 'required|in:individual,corporation,foundation,alumni,parent,other',
            'donation_type' => 'required|in:monetary,equipment,supplies,services,property,other',
            'donation_amount' => 'nullable|numeric|min:0',
            'donation_description' => 'required|string|max:1000',
            'donation_date' => 'required|date|before_or_equal:today',
            'acknowledgment_sent' => 'boolean',
            'acknowledgment_date' => 'nullable|date|after:donation_date',
            'tax_receipt_issued' => 'boolean',
            'tax_receipt_date' => 'nullable|date|after:donation_date',
            'restrictions' => 'nullable|string|max:500',
            'designated_use' => 'nullable|string|max:200',
            'recognition_level' => 'nullable|string|max:100',
            'anonymous_donation' => 'boolean',
            'contact_person' => 'nullable|string|max:200',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:100'
        ];
    }

    /**
     * Alumni relations validation rules
     */
    private static function getAlumniRelationsRules(): array
    {
        return [
            'alumni_name' => 'required|string|max:200',
            'graduation_year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'degree_program' => 'required|string|max:200',
            'current_occupation' => 'nullable|string|max:200',
            'employer' => 'nullable|string|max:200',
            'contact_email' => 'required|email|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'mailing_address' => 'nullable|string|max:500',
            'alumni_activities' => 'nullable|array',
            'alumni_activities.*' => 'string|max:200',
            'volunteer_interest' => 'boolean',
            'volunteer_areas' => 'nullable|array',
            'volunteer_areas.*' => 'string|max:200',
            'mentorship_interest' => 'boolean',
            'mentorship_areas' => 'nullable|array',
            'mentorship_areas.*' => 'string|max:200',
            'donation_history' => 'nullable|array',
            'donation_history.*.year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'donation_history.*.amount' => 'required|numeric|min:0',
            'newsletter_subscription' => 'boolean',
            'event_notifications' => 'boolean'
        ];
    }

    /**
     * Community outreach validation rules
     */
    private static function getCommunityOutreachRules(): array
    {
        return [
            'program_name' => 'required|string|max:200',
            'program_type' => 'required|in:volunteer,partnership,event,education,health,environmental,other',
            'target_community' => 'required|string|max:200',
            'program_description' => 'required|string|max:1000',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'duration_hours' => 'nullable|integer|min:1',
            'location' => 'required|string|max:200',
            'participants_expected' => 'nullable|integer|min:1',
            'participants_actual' => 'nullable|integer|min:0',
            'volunteers_needed' => 'nullable|integer|min:0',
            'volunteers_registered' => 'nullable|integer|min:0',
            'budget' => 'nullable|numeric|min:0',
            'funding_source' => 'nullable|string|max:200',
            'partnerships' => 'nullable|array',
            'partnerships.*' => 'string|max:200',
            'success_metrics' => 'nullable|array',
            'success_metrics.*' => 'string|max:200',
            'challenges_faced' => 'nullable|string|max:500',
            'lessons_learned' => 'nullable|string|max:500',
            'follow_up_required' => 'boolean',
            'next_steps' => 'nullable|string|max:500'
        ];
    }

    /**
     * Partnership management validation rules
     */
    private static function getPartnershipManagementRules(): array
    {
        return [
            'partner_name' => 'required|string|max:200',
            'partner_type' => 'required|in:business,educational,governmental,non_profit,community,other',
            'partnership_type' => 'required|in:sponsorship,mentorship,internship,research,community_service,other',
            'partnership_start_date' => 'required|date|before_or_equal:today',
            'partnership_end_date' => 'nullable|date|after:partnership_start_date',
            'partnership_status' => 'required|in:active,inactive,pending,expired,terminated',
            'contact_person' => 'required|string|max:200',
            'contact_title' => 'nullable|string|max:100',
            'contact_phone' => 'required|string|max:20',
            'contact_email' => 'required|email|max:100',
            'partnership_goals' => 'required|array|min:1',
            'partnership_goals.*' => 'string|max:500',
            'benefits_for_school' => 'required|array|min:1',
            'benefits_for_school.*' => 'string|max:500',
            'benefits_for_partner' => 'required|array|min:1',
            'benefits_for_partner.*' => 'string|max:500',
            'financial_contribution' => 'nullable|numeric|min:0',
            'resource_contribution' => 'nullable|string|max:500',
            'evaluation_criteria' => 'nullable|array',
            'evaluation_criteria.*' => 'string|max:200',
            'renewal_terms' => 'nullable|string|max:500',
            'termination_clause' => 'nullable|string|max:500'
        ];
    }
}
