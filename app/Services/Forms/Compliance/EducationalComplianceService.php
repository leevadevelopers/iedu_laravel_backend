<?php

namespace App\Services\Forms\Compliance;

use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EducationalComplianceService
{
    /**
     * Check compliance for educational forms
     */
    public function checkCompliance(FormInstance $formInstance, array $context = []): array
    {
        $template = $formInstance->template;
        $category = $template->category;
        $formData = $formInstance->form_data;

        $cacheKey = "compliance_check_{$formInstance->id}_{$category}";

        return Cache::remember($cacheKey, 1800, function () use ($category, $formData, $context) {
            $complianceResults = [
                'overall_compliance' => true,
                'compliance_score' => 100,
                'violations' => [],
                'warnings' => [],
                'recommendations' => [],
                'category_specific' => []
            ];

            // Check base educational compliance
            $baseCompliance = $this->checkBaseCompliance($formData, $context);
            $complianceResults = $this->mergeComplianceResults($complianceResults, $baseCompliance);

            // Check category-specific compliance
            $categoryCompliance = $this->checkCategoryCompliance($category, $formData, $context);
            $complianceResults = $this->mergeComplianceResults($complianceResults, $categoryCompliance);

            // Check regulatory compliance
            $regulatoryCompliance = $this->checkRegulatoryCompliance($category, $formData, $context);
            $complianceResults = $this->mergeComplianceResults($complianceResults, $regulatoryCompliance);

            // Calculate final compliance score
            $complianceResults['compliance_score'] = $this->calculateComplianceScore($complianceResults);
            $complianceResults['overall_compliance'] = $complianceResults['compliance_score'] >= 80;

            return $complianceResults;
        });
    }

    /**
     * Check base educational compliance rules
     */
    private function checkBaseCompliance(array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check required educational fields
        $requiredFields = ['academic_year', 'school_code'];
        foreach ($requiredFields as $field) {
            if (empty($formData[$field] ?? null)) {
                $violations[] = "Required educational field '{$field}' is missing";
            }
        }

        // Check academic year format (YYYY-YYYY)
        if (isset($formData['academic_year']) && !preg_match('/^\d{4}-\d{4}$/', $formData['academic_year'])) {
            $violations[] = "Academic year must be in format YYYY-YYYY";
        }

        // Check school code format
        if (isset($formData['school_code']) && !preg_match('/^[A-Z0-9]{3,20}$/', $formData['school_code'])) {
            $warnings[] = "School code should be 3-20 alphanumeric characters";
        }

        // Check date validations
        if (isset($formData['academic_year'])) {
            $yearParts = explode('-', $formData['academic_year']);
            if (count($yearParts) === 2) {
                $startYear = (int)$yearParts[0];
                $endYear = (int)$yearParts[1];
                if ($endYear !== $startYear + 1) {
                    $violations[] = "Academic year must be consecutive years (e.g., 2024-2025)";
                }
            }
        }

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check category-specific compliance
     */
    private function checkCategoryCompliance(string $category, array $formData, array $context): array
    {
        $methodName = 'check' . str_replace('_', '', ucwords($category)) . 'Compliance';

        if (method_exists($this, $methodName)) {
            return $this->$methodName($formData, $context);
        }

        return ['violations' => [], 'warnings' => [], 'recommendations' => []];
    }

    /**
     * Check student enrollment compliance
     */
    private function checkStudentEnrollmentCompliance(array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check age requirements
        if (isset($formData['date_of_birth'])) {
            $birthDate = \Carbon\Carbon::parse($formData['date_of_birth']);
            $age = $birthDate->age;

            if ($age < 3 || $age > 25) {
                $warnings[] = "Student age ({$age}) is outside typical school age range";
            }
        }

        // Check required documents
        $requiredDocs = $formData['documents_required'] ?? [];
        $criticalDocs = ['birth_certificate', 'id_card'];
        foreach ($criticalDocs as $doc) {
            if (!in_array($doc, $requiredDocs)) {
                $violations[] = "Critical document '{$doc}' is required for enrollment";
            }
        }

        // Check medical information
        if (!empty($formData['medical_conditions'] ?? '') || !empty($formData['special_needs'] ?? '')) {
            $recommendations[] = "Schedule meeting with health office and special education coordinator";
        }

        // Check contact information
        if (empty($formData['parent_guardian_phone'] ?? '') && empty($formData['parent_guardian_email'] ?? '')) {
            $violations[] = "At least one parent/guardian contact method is required";
        }

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check attendance compliance
     */
    private function checkAttendanceCompliance(array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check attendance date
        if (isset($formData['attendance_date'])) {
            $attendanceDate = \Carbon\Carbon::parse($formData['attendance_date']);
            $today = \Carbon\Carbon::today();

            if ($attendanceDate->gt($today)) {
                $violations[] = "Attendance date cannot be in the future";
            }
        }

        // Check absence documentation
        if (($formData['attendance_status'] ?? '') === 'absent' || ($formData['attendance_status'] ?? '') === 'excused') {
            if (empty($formData['absence_reason'] ?? '')) {
                $violations[] = "Absence reason is required for absent/excused attendance";
            }
        }

        // Check doctor's note requirement
        if (($formData['attendance_status'] ?? '') === 'excused' && !($formData['doctor_note'] ?? false)) {
            $warnings[] = "Consider requiring doctor's note for extended absences";
        }

        // Check makeup work assignment
        if (($formData['attendance_status'] ?? '') === 'absent' && !($formData['makeup_work_assigned'] ?? false)) {
            $recommendations[] = "Assign makeup work for absent students";
        }

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check grades compliance
     */
    private function checkGradesCompliance(array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check grade value range
        if (isset($formData['grade_value'])) {
            $grade = (float)$formData['grade_value'];
            if ($grade < 0 || $grade > 100) {
                $violations[] = "Grade value must be between 0 and 100";
            }

            if ($grade < 60) {
                $warnings[] = "Low grade recorded. Consider academic intervention";
            }
        }

        // Check grading date
        if (isset($formData['graded_at'])) {
            $gradedDate = \Carbon\Carbon::parse($formData['graded_at']);
            $today = \Carbon\Carbon::today();

            if ($gradedDate->gt($today)) {
                $violations[] = "Grading date cannot be in the future";
            }
        }

        // Check late submission handling
        if (($formData['late_submission'] ?? false)) {
            $recommendations[] = "Document late submission policy and apply appropriate penalties";
        }

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check behavior incident compliance
     */
    private function checkBehaviorIncidentCompliance(array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check incident date
        if (isset($formData['incident_date'])) {
            $incidentDate = \Carbon\Carbon::parse($formData['incident_date']);
            $today = \Carbon\Carbon::today();

            if ($incidentDate->gt($today)) {
                $violations[] = "Incident date cannot be in the future";
            }
        }

        // Check severity level handling
        $severity = $formData['severity_level'] ?? '';
        if (in_array($severity, ['major', 'severe'])) {
            if (empty($formData['disciplinary_action'] ?? '')) {
                $violations[] = "Disciplinary action is required for major/severe incidents";
            }

            if (!($formData['parent_notified'] ?? false)) {
                $violations[] = "Parent notification is required for major/severe incidents";
            }
        }

        // Check follow-up requirements
        if (($formData['follow_up_required'] ?? false) && empty($formData['follow_up_date'] ?? '')) {
            $violations[] = "Follow-up date is required when follow-up is needed";
        }

        // Check witness documentation
        if (empty($formData['witnesses'] ?? [])) {
            $warnings[] = "Consider documenting witnesses for incident reports";
        }

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check special education compliance
     */
    private function checkSpecialEducationCompliance(array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check IEP requirements
        if (empty($formData['iep_goals'] ?? [])) {
            $violations[] = "IEP goals are required for special education students";
        }

        if (empty($formData['accommodations'] ?? [])) {
            $violations[] = "Accommodations are required for special education students";
        }

        // Check parent consent
        if (!($formData['parent_consent'] ?? false)) {
            $violations[] = "Parent consent is required for special education services";
        }

        // Check review date
        if (isset($formData['review_date'])) {
            $reviewDate = \Carbon\Carbon::parse($formData['review_date']);
            $today = \Carbon\Carbon::today();

            if ($reviewDate->lte($today)) {
                $warnings[] = "IEP review date should be in the future";
            }
        }

        // Check professional credentials
        if (empty($formData['professional_credentials'] ?? '')) {
            $warnings[] = "Document professional credentials of diagnosing professional";
        }

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check field trip compliance
     */
    private function checkFieldTripCompliance(array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check trip date
        if (isset($formData['trip_date'])) {
            $tripDate = \Carbon\Carbon::parse($formData['trip_date']);
            $today = \Carbon\Carbon::today();

            if ($tripDate->lte($today)) {
                $violations[] = "Field trip date must be in the future";
            }
        }

        // Check chaperone requirements
        $chaperones = $formData['chaperones'] ?? [];
        $maxStudents = $formData['maximum_students'] ?? 0;
        $requiredChaperones = max(1, ceil($maxStudents / 15)); // 1 chaperone per 15 students

        if (count($chaperones) < $requiredChaperones) {
            $violations[] = "Insufficient chaperones for student count";
        }

        // Check safety considerations
        if (empty($formData['safety_considerations'] ?? '')) {
            $violations[] = "Safety considerations are required for field trips";
        }

        // Check emergency contact
        if (empty($formData['emergency_contact'] ?? '') || empty($formData['emergency_phone'] ?? '')) {
            $violations[] = "Emergency contact information is required for field trips";
        }

        // Check parent consent
        if (($formData['parent_consent_required'] ?? false)) {
            $recommendations[] = "Ensure parent consent forms are collected before trip";
        }

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check regulatory compliance
     */
    private function checkRegulatoryCompliance(string $category, array $formData, array $context): array
    {
        $violations = [];
        $warnings = [];
        $recommendations = [];

        // Check FERPA compliance (Family Educational Rights and Privacy Act)
        $ferpaViolations = $this->checkFERPACompliance($category, $formData);
        $violations = array_merge($violations, $ferpaViolations);

        // Check IDEA compliance (Individuals with Disabilities Education Act)
        if ($category === 'special_education') {
            $ideaViolations = $this->checkIDEACompliance($formData);
            $violations = array_merge($violations, $ideaViolations);
        }

        // Check Section 504 compliance
        if (in_array($category, ['student_health', 'special_education'])) {
            $section504Violations = $this->checkSection504Compliance($formData);
            $violations = array_merge($violations, $section504Violations);
        }

        // Check state-specific regulations
        $stateViolations = $this->checkStateRegulations($category, $formData, $context);
        $violations = array_merge($violations, $stateViolations);

        return [
            'violations' => $violations,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check FERPA compliance
     */
    private function checkFERPACompliance(string $category, array $formData): array
    {
        $violations = [];

        // Check for unauthorized disclosure of student information
        $sensitiveFields = ['ssn', 'social_security', 'tax_id', 'financial_info'];
        foreach ($sensitiveFields as $field) {
            if (isset($formData[$field]) && !empty($formData[$field])) {
                $violations[] = "FERPA violation: Sensitive personal information should not be collected";
            }
        }

        // Check for proper access controls
        if (isset($formData['public_access_enabled']) && $formData['public_access_enabled']) {
            $violations[] = "FERPA violation: Public access to student records is prohibited";
        }

        return $violations;
    }

    /**
     * Check IDEA compliance
     */
    private function checkIDEACompliance(array $formData): array
    {
        $violations = [];

        // Check for required IEP components
        if (empty($formData['iep_goals'] ?? [])) {
            $violations[] = "IDEA violation: IEP goals are required";
        }

        if (empty($formData['accommodations'] ?? [])) {
            $violations[] = "IDEA violation: Accommodations are required";
        }

        if (empty($formData['placement_setting'] ?? '')) {
            $violations[] = "IDEA violation: Placement setting must be specified";
        }

        return $violations;
    }

    /**
     * Check Section 504 compliance
     */
    private function checkSection504Compliance(array $formData): array
    {
        $violations = [];

        // Check for reasonable accommodations
        if (isset($formData['special_needs']) && !empty($formData['special_needs'])) {
            if (empty($formData['accommodations'] ?? [])) {
                $violations[] = "Section 504 violation: Reasonable accommodations must be provided";
            }
        }

        return $violations;
    }

    /**
     * Check state-specific regulations
     */
    private function checkStateRegulations(string $category, array $formData, array $context): array
    {
        $violations = [];

        // This would be implemented based on specific state requirements
        // For now, return empty array as placeholder

        return $violations;
    }

    /**
     * Merge compliance results
     */
    private function mergeComplianceResults(array $main, array $new): array
    {
        return [
            'overall_compliance' => $main['overall_compliance'],
            'compliance_score' => $main['compliance_score'],
            'violations' => array_merge($main['violations'], $new['violations']),
            'warnings' => array_merge($main['warnings'], $new['warnings']),
            'recommendations' => array_merge($main['recommendations'], $new['recommendations']),
            'category_specific' => $main['category_specific']
        ];
    }

    /**
     * Calculate compliance score
     */
    private function calculateComplianceScore(array $results): int
    {
        $baseScore = 100;

        // Deduct points for violations
        $violationPenalty = count($results['violations']) * 10;

        // Deduct points for warnings
        $warningPenalty = count($results['warnings']) * 2;

        $finalScore = $baseScore - $violationPenalty - $warningPenalty;

        return max(0, $finalScore);
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport(FormInstance $formInstance): array
    {
        $compliance = $this->checkCompliance($formInstance);

        return [
            'form_instance_id' => $formInstance->id,
            'template_category' => $formInstance->template->category,
            'compliance_date' => now()->toISOString(),
            'compliance_results' => $compliance,
            'summary' => [
                'status' => $compliance['overall_compliance'] ? 'compliant' : 'non_compliant',
                'score' => $compliance['compliance_score'],
                'total_violations' => count($compliance['violations']),
                'total_warnings' => count($compliance['warnings']),
                'total_recommendations' => count($compliance['recommendations'])
            ]
        ];
    }
}
