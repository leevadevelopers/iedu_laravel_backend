<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student Document Resource
 * 
 * Transforms student document data for API responses with proper
 * privacy controls and FERPA compliance.
 */
class StudentDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            // Document Identity
            'id' => $this->id,
            'student_id' => $this->student_id,
            'document_name' => $this->document_name,
            'document_type' => $this->document_type,
            'document_category' => $this->document_category,
            
            // File Information
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'formatted_file_size' => $this->getFormattedFileSize(),
            'mime_type' => $this->mime_type,
            
            // Document Status
            'status' => $this->status,
            'is_verified' => $this->isVerified(),
            'verified' => $this->verified,
            'required' => $this->required,
            'ferpa_protected' => $this->ferpa_protected,
            
            // Expiration Information
            'expiration_date' => $this->expiration_date?->format('Y-m-d'),
            'is_expired' => $this->isExpired(),
            'days_until_expiration' => $this->expiration_date ? 
                now()->diffInDays($this->expiration_date, false) : null,
            
            // Processing Information
            'uploaded_by' => $this->whenLoaded('uploader', function () {
                return [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->first_name . ' ' . $this->uploader->last_name,
                    'role' => $this->uploader->user_type,
                ];
            }),
            'verified_by' => $this->whenLoaded('verifier', function () {
                return $this->verifier ? [
                    'id' => $this->verifier->id,
                    'name' => $this->verifier->first_name . ' ' . $this->verifier->last_name,
                    'role' => $this->verifier->user_type,
                ] : null;
            }),
            'verified_at' => $this->verified_at?->toISOString(),
            'verification_notes' => $this->verification_notes,
            
            // Access Information
            'download_url' => $this->when(
                $this->canDownload($request),
                $this->getDownloadUrl()
            ),
            'can_access' => $this->canBeAccessedBy($request->user()),
            
            // Privacy & Access Controls
            'access_permissions' => $this->access_permissions_json,
            
            // Student Information (when needed)
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_number' => $this->student->student_number,
                    'full_name' => $this->student->full_name,
                    'current_grade_level' => $this->student->current_grade_level,
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Check if the current user can download this document.
     */
    protected function canDownload(Request $request): bool
    {
        $user = $request->user();
        
        // Use the model's built-in access control
        return $this->canBeAccessedBy($user);
    }

    /**
     * Get additional data when requested.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'permissions' => [
                    'can_download' => $this->canDownload($request),
                    'can_edit' => $request->user()?->can('update', $this->resource),
                    'can_delete' => $request->user()?->can('delete', $this->resource),
                    'can_verify' => $request->user()?->can('verify', $this->resource),
                ]
            ]
        ];
    }
}
