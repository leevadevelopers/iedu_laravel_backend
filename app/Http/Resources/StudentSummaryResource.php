<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student Summary Resource
 * 
 * Provides a lightweight student data representation for listing views,
 * dashboards, and quick reference with essential information only.
 */
class StudentSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            // Essential Identity
            'id' => $this->id,
            'student_number' => $this->student_number,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            
            // Academic Status
            'current_grade_level' => $this->current_grade_level,
            'enrollment_status' => $this->enrollment_status,
            'is_enrolled' => $this->isEnrolled(),
            
            // Key Metrics
            'current_gpa' => $this->current_gpa ? (float) $this->current_gpa : null,
            'attendance_rate' => $this->attendance_rate ? (float) $this->attendance_rate : null,
            'age' => $this->age,
            
            // Contact Information (basic)
            'email' => $this->email,
            'phone' => $this->phone,
            
            // Status Indicators
            'has_special_needs' => $this->hasSpecialNeeds(),
            'has_medical_alerts' => !empty($this->medical_information_json),
            'missing_emergency_contacts' => empty($this->emergency_contacts_json),
            
            // Family Contact (primary only)
            'primary_contact' => $this->whenLoaded('familyRelationships', function () {
                $primary = $this->familyRelationships->where('primary_contact', true)->first();
                return $primary ? [
                    'name' => $primary->guardian->first_name . ' ' . $primary->guardian->last_name,
                    'relationship' => $primary->getFormattedRelationshipType(),
                    'phone' => $primary->guardian->phone,
                    'email' => $primary->guardian->email,
                ] : null;
            }),
            
            // Academic Year
            'current_academic_year' => $this->whenLoaded('currentAcademicYear', function () {
                return [
                    'id' => $this->currentAcademicYear->id,
                    'name' => $this->currentAcademicYear->name,
                ];
            }),
            
            // Document Status Summary
            'documents_status' => $this->whenLoaded('documents', function () {
                $documents = $this->documents;
                $requiredCount = $documents->where('required', true)->count();
                $verifiedRequiredCount = $documents->where('required', true)->where('verified', true)->count();
                
                return [
                    'required_complete' => $requiredCount > 0 ? ($verifiedRequiredCount === $requiredCount) : true,
                    'required_pending' => $requiredCount - $verifiedRequiredCount,
                    'total_documents' => $documents->count(),
                ];
            }),
            
            // Quick Action Flags
            'needs_attention' => $this->needsAttention(),
            'upcoming_birthday' => $this->date_of_birth && 
                $this->date_of_birth->format('m-d') >= now()->format('m-d') &&
                $this->date_of_birth->format('m-d') <= now()->addDays(30)->format('m-d'),
            
            // Timestamps (essential only)
            'admission_date' => $this->admission_date?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Check if student needs attention (for dashboard alerts).
     */
    protected function needsAttention(): bool
    {
        // Low attendance
        if ($this->attendance_rate && $this->attendance_rate < 90) {
            return true;
        }
        
        // Low GPA
        if ($this->current_gpa && $this->current_gpa < 2.0) {
            return true;
        }
        
        // Missing emergency contacts
        if (empty($this->emergency_contacts_json)) {
            return true;
        }
        
        // Missing required documents
        if ($this->relationLoaded('documents')) {
            $hasIncompleteRequiredDocs = $this->documents
                ->where('required', true)
                ->where('verified', false)
                ->isNotEmpty();
                
            if ($hasIncompleteRequiredDocs) {
                return true;
            }
        }
        
        return false;
    }
}
