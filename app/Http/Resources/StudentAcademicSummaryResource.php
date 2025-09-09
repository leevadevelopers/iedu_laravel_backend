<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student Academic Summary Resource
 *
 * Provides detailed academic performance and progress information
 * for educational reporting and analysis.
 */
class StudentAcademicSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            // Student Identity
            'student_id' => $this->resource['student_id'] ?? $this->id,
            'student_number' => $this->student_number ?? null,
            'full_name' => $this->full_name ?? null,

            // Academic Performance Metrics
            'current_gpa' => isset($this->resource['current_gpa']) ?
                (float) $this->resource['current_gpa'] :
                ($this->current_gpa ? (float) $this->current_gpa : null),

            'attendance_rate' => isset($this->resource['attendance_rate']) ?
                (float) $this->resource['attendance_rate'] :
                ($this->attendance_rate ? (float) $this->attendance_rate : null),

            // Attendance Details
            'attendance_summary' => [
                'total_days' => $this->resource['total_attendance_days'] ?? 0,
                'present_days' => $this->resource['present_days'] ?? 0,
                'absent_days' => $this->resource['absent_days'] ?? 0,
                'attendance_rate_percentage' => isset($this->resource['attendance_rate']) ?
                    round($this->resource['attendance_rate'], 1) : null,
            ],

            // Behavioral Information
            'behavioral_summary' => [
                'current_points' => $this->resource['behavioral_points'] ?? $this->behavioral_points ?? 0,
                'incidents_this_year' => $this->resource['behavioral_incidents_count'] ?? 0,
            ],

            // Academic Engagement
            'academic_engagement' => [
                'grades_recorded' => $this->resource['grades_count'] ?? 0,
                'enrollment_status' => $this->resource['enrollment_status'] ?? $this->enrollment_status ?? 'unknown',
                'current_grade_level' => $this->resource['current_grade_level'] ?? $this->current_grade_level ?? 'unknown',
            ],

            // Enrollment Information
            'enrollment_summary' => [
                'status' => $this->resource['enrollment_status'] ?? $this->enrollment_status ?? 'unknown',
                'grade_level' => $this->resource['current_grade_level'] ?? $this->current_grade_level ?? 'unknown',
                'enrollment_duration_days' => $this->resource['enrollment_duration_days'] ?? null,
                'age' => $this->resource['age'] ?? $this->age ?? null,
            ],

            // Special Considerations
            'special_considerations' => [
                'has_special_needs' => $this->resource['has_special_needs'] ?? $this->hasSpecialNeeds() ?? false,
                'accommodation_needs' => $this->accommodation_needs_json ?? null,
                'language_profile' => $this->language_profile_json ?? null,
            ],

            // Emergency Contact Status
            'emergency_contact_status' => [
                'primary_contact' => $this->resource['primary_emergency_contact'] ?? null,
                'has_emergency_contacts' => !empty($this->emergency_contacts_json ?? []),
            ],

            // Academic Alerts
            'academic_alerts' => $this->generateAcademicAlerts(),

            // Performance Classification
            'performance_classification' => $this->getPerformanceClassification(),

            // Summary Statistics
            'summary_statistics' => [
                'overall_score' => $this->calculateOverallScore(),
                'risk_level' => $this->calculateRiskLevel(),
                'intervention_recommended' => $this->requiresIntervention(),
            ],
        ];
    }

    /**
     * Generate academic alerts based on performance data.
     */
    protected function generateAcademicAlerts(): array
    {
        $alerts = [];

        // GPA alerts
        $gpa = $this->resource['current_gpa'] ?? $this->current_gpa ?? 0;
        if ($gpa && $gpa < 2.0) {
            $alerts[] = [
                'type' => 'academic_performance',
                'severity' => 'high',
                'message' => 'GPA below 2.0 - immediate intervention recommended'
            ];
        } elseif ($gpa && $gpa < 2.5) {
            $alerts[] = [
                'type' => 'academic_performance',
                'severity' => 'medium',
                'message' => 'GPA below 2.5 - monitoring recommended'
            ];
        }

        // Attendance alerts
        $attendance = $this->resource['attendance_rate'] ?? $this->attendance_rate ?? 100;
        if ($attendance && $attendance < 85) {
            $alerts[] = [
                'type' => 'attendance',
                'severity' => 'high',
                'message' => 'Chronic absenteeism - attendance below 85%'
            ];
        } elseif ($attendance && $attendance < 90) {
            $alerts[] = [
                'type' => 'attendance',
                'severity' => 'medium',
                'message' => 'Low attendance - below 90%'
            ];
        }

        // Behavioral alerts
        $incidents = $this->resource['behavioral_incidents_count'] ?? 0;
        if ($incidents >= 5) {
            $alerts[] = [
                'type' => 'behavioral',
                'severity' => 'high',
                'message' => 'Multiple behavioral incidents this year'
            ];
        }

        return $alerts;
    }

    /**
     * Get performance classification.
     */
    protected function getPerformanceClassification(): string
    {
        $gpa = $this->resource['current_gpa'] ?? $this->current_gpa ?? 0;
        $attendance = $this->resource['attendance_rate'] ?? $this->attendance_rate ?? 100;

        if ($gpa >= 3.5 && $attendance >= 95) {
            return 'excellent';
        } elseif ($gpa >= 3.0 && $attendance >= 90) {
            return 'good';
        } elseif ($gpa >= 2.0 && $attendance >= 85) {
            return 'satisfactory';
        } elseif ($gpa >= 1.5 && $attendance >= 75) {
            return 'needs_improvement';
        } else {
            return 'at_risk';
        }
    }

    /**
     * Calculate overall performance score (0-100).
     */
    protected function calculateOverallScore(): float
    {
        $gpa = $this->resource['current_gpa'] ?? $this->current_gpa ?? 0;
        $attendance = $this->resource['attendance_rate'] ?? $this->attendance_rate ?? 100;
        $incidents = $this->resource['behavioral_incidents_count'] ?? 0;

        // Weight: GPA (50%), Attendance (40%), Behavior (10%)
        $gpaScore = ($gpa / 4.0) * 50;
        $attendanceScore = ($attendance / 100) * 40;
        $behaviorScore = max(0, (10 - $incidents)) * 1; // Deduct 1 point per incident

        return min(100, $gpaScore + $attendanceScore + $behaviorScore);
    }

    /**
     * Calculate risk level for intervention.
     */
    protected function calculateRiskLevel(): string
    {
        $overallScore = $this->calculateOverallScore();

        if ($overallScore >= 80) {
            return 'low';
        } elseif ($overallScore >= 60) {
            return 'medium';
        } elseif ($overallScore >= 40) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    /**
     * Determine if student requires intervention.
     */
    protected function requiresIntervention(): bool
    {
        return in_array($this->calculateRiskLevel(), ['high', 'critical']);
    }
}
