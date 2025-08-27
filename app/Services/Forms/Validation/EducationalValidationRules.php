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
}
