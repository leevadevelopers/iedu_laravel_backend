<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student Resource
 * 
 * Transforms student data for API responses with proper data formatting,
 * privacy controls, and educational context information.
 */
class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic Identity
            'id' => $this->id,
            'student_number' => $this->student_number,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            
            // Personal Information
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'birth_place' => $this->birth_place,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'age' => $this->age,
            
            // Contact Information
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address_json,
            
            // Academic Information
            'admission_date' => $this->admission_date?->format('Y-m-d'),
            'current_grade_level' => $this->current_grade_level,
            'enrollment_status' => $this->enrollment_status,
            'expected_graduation_date' => $this->expected_graduation_date?->format('Y-m-d'),
            
            // Academic Performance (cached values)
            'current_gpa' => $this->current_gpa ? (float) $this->current_gpa : null,
            'attendance_rate' => $this->attendance_rate ? (float) $this->attendance_rate : null,
            'behavioral_points' => $this->behavioral_points,
            
            // Educational Profile
            'learning_profile' => $this->learning_profile_json,
            'accommodation_needs' => $this->accommodation_needs_json,
            'language_profile' => $this->language_profile_json,
            'has_special_needs' => $this->hasSpecialNeeds(),
            
            // Health & Safety (filtered based on permissions)
            'medical_information' => $this->when(
                $this->canViewMedicalInfo($request),
                $this->medical_information_json
            ),
            'emergency_contacts' => $this->emergency_contacts_json,
            'special_circumstances' => $this->when(
                $this->canViewSpecialCircumstances($request),
                $this->special_circumstances_json
            ),
            
            // Academic Context
            'current_academic_year' => $this->whenLoaded('currentAcademicYear', function () {
                return [
                    'id' => $this->currentAcademicYear->id,
                    'name' => $this->currentAcademicYear->name,
                    'start_date' => $this->currentAcademicYear->start_date?->format('Y-m-d'),
                    'end_date' => $this->currentAcademicYear->end_date?->format('Y-m-d'),
                ];
            }),
            
            // Family Relationships
            'family_relationships' => FamilyRelationshipResource::collection($this->whenLoaded('familyRelationships')),
            'primary_contact' => $this->whenLoaded('familyRelationships', function () {
                $primary = $this->familyRelationships->where('primary_contact', true)->first();
                return $primary ? new FamilyRelationshipResource($primary) : null;
            }),
            
            // Documents Summary
            'documents_summary' => $this->whenLoaded('documents', function () {
                $documents = $this->documents;
                return [
                    'total_count' => $documents->count(),
                    'verified_count' => $documents->where('verified', true)->count(),
                    'required_pending' => $documents->where('required', true)->where('verified', false)->count(),
                    'expired_count' => $documents->filter(fn($doc) => $doc->isExpired())->count(),
                ];
            }),
            
            // Status Flags
            'is_enrolled' => $this->isEnrolled(),
            'enrollment_duration_days' => $this->admission_date ? $this->admission_date->diffInDays(now()) : null,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Conditional data based on user permissions
            'sensitive_data' => $this->when(
                $this->canViewSensitiveData($request),
                [
                    'government_id' => $this->government_id,
                    'detailed_medical' => $this->medical_information_json,
                    'custody_information' => $this->special_circumstances_json,
                ]
            ),
        ];
    }

    /**
     * Check if the current user can view medical information.
     */
    protected function canViewMedicalInfo(Request $request): bool
    {
        $user = $request->user();
        
        // School administrators and nurses can view medical info
        if ($user->hasRole(['school_admin', 'nurse', 'principal'])) {
            return true;
        }
        
        // Parents can view their child's medical info if they have medical access
        if ($user->hasRole('parent')) {
            return $this->familyRelationships()
                ->where('guardian_user_id', $user->id)
                ->where('medical_access', true)
                ->where('status', 'active')
                ->exists();
        }
        
        return false;
    }

    /**
     * Check if the current user can view special circumstances.
     */
    protected function canViewSpecialCircumstances(Request $request): bool
    {
        $user = $request->user();
        
        // Only administrators and counselors can view special circumstances
        return $user->hasRole(['school_admin', 'principal', 'counselor']);
    }

    /**
     * Check if the current user can view sensitive data.
     */
    protected function canViewSensitiveData(Request $request): bool
    {
        $user = $request->user();
        
        // Only system and school administrators
        return $user->hasRole(['super_admin', 'school_admin', 'principal']);
    }

    /**
     * Get additional data when requested.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'permissions' => [
                    'can_view_medical' => $this->canViewMedicalInfo($request),
                    'can_view_sensitive' => $this->canViewSensitiveData($request),
                    'can_edit' => $request->user()?->can('update', $this->resource),
                    'can_delete' => $request->user()?->can('delete', $this->resource),
                ]
            ]
        ];
    }
}
