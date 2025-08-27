<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Family Relationship Resource
 * 
 * Transforms family relationship data for API responses with proper
 * privacy controls and educational access information.
 */
class FamilyRelationshipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic Relationship Information
            'id' => $this->id,
            'student_id' => $this->student_id,
            'guardian_user_id' => $this->guardian_user_id,
            
            // Relationship Definition
            'relationship_type' => $this->relationship_type,
            'relationship_description' => $this->relationship_description,
            'formatted_relationship_type' => $this->getFormattedRelationshipType(),
            
            // Contact & Emergency Status
            'primary_contact' => $this->primary_contact,
            'emergency_contact' => $this->emergency_contact,
            'pickup_authorized' => $this->pickup_authorized,
            
            // Educational Access Permissions
            'academic_access' => $this->academic_access,
            'medical_access' => $this->medical_access,
            'has_academic_access' => $this->hasAcademicAccess(),
            'has_medical_access' => $this->hasMedicalAccess(),
            
            // Legal & Financial Information
            'custody_rights' => $this->custody_rights,
            'custody_details' => $this->when(
                $this->canViewCustodyDetails($request),
                $this->custody_details_json
            ),
            'financial_responsibility' => $this->financial_responsibility,
            
            // Communication Preferences
            'communication_preferences' => $this->communication_preferences_json,
            
            // Status
            'status' => $this->status,
            'is_active' => $this->status === 'active',
            
            // Related Data
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_number' => $this->student->student_number,
                    'full_name' => $this->student->full_name,
                    'display_name' => $this->student->display_name,
                    'current_grade_level' => $this->student->current_grade_level,
                    'enrollment_status' => $this->student->enrollment_status,
                ];
            }),
            
            'guardian' => $this->whenLoaded('guardian', function () {
                return [
                    'id' => $this->guardian->id,
                    'full_name' => $this->guardian->first_name . ' ' . $this->guardian->last_name,
                    'email' => $this->guardian->email,
                    'phone' => $this->guardian->phone,
                    'preferred_name' => $this->guardian->preferred_name,
                ];
            }),
            
            // Capability Flags
            'can_pickup_student' => $this->canPickupStudent(),
            'is_primary_contact' => $this->isPrimaryContact(),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Check if the current user can view custody details.
     */
    protected function canViewCustodyDetails(Request $request): bool
    {
        $user = $request->user();
        
        // School administrators can view custody details
        if ($user->hasRole(['school_admin', 'principal', 'counselor'])) {
            return true;
        }
        
        // Guardian can view their own custody details
        if ($user->hasRole('parent') && $user->id === $this->guardian_user_id) {
            return true;
        }
        
        return false;
    }

    /**
     * Get additional data when requested.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'permissions' => [
                    'can_view_custody' => $this->canViewCustodyDetails($request),
                    'can_edit' => $request->user()?->can('update', $this->resource),
                    'can_delete' => $request->user()?->can('delete', $this->resource),
                ]
            ]
        ];
    }
}
