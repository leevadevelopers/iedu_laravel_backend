<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

abstract class BaseAcademicRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        // Default authorization - user must be authenticated and have school association
        return Auth::check() && $this->hasValidSchoolAssociation();
    }

    /**
     * Check if user has valid school association
     */
    protected function hasValidSchoolAssociation(): bool
    {
        try {
            $user = Auth::user();
            return $user && $user->activeSchools()->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the current school ID from user's school_users relationship
     */
    protected function getCurrentSchoolId(): int
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $schoolUser = $user->activeSchools()->first();

        if (!$schoolUser) {
            throw new \Exception('User is not associated with any schools');
        }

        return $schoolUser->school_id;
    }

    /**
     * Get the current school ID safely, returning null if not available
     */
    protected function getCurrentSchoolIdOrNull(): ?int
    {
        try {
            return $this->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the current tenant ID safely, returning null if not available
     */
    protected function getCurrentTenantIdOrNull(): ?int
    {
        try {
            $user = Auth::user();
            return $user?->tenant_id;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Common validation messages
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be a string.',
            'integer' => 'The :attribute must be an integer.',
            'numeric' => 'The :attribute must be a number.',
            'date' => 'The :attribute must be a valid date.',
            'email' => 'The :attribute must be a valid email address.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'in' => 'The selected :attribute is invalid.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'between' => 'The :attribute must be between :min and :max.',
            'array' => 'The :attribute must be an array.',
            'json' => 'The :attribute must be valid JSON.',
        ];
    }

    /**
     * Custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return [
            'school_id' => 'school',
            'academic_year_id' => 'academic year',
            'academic_term_id' => 'academic term',
            'subject_id' => 'subject',
            'class_id' => 'class',
            'student_id' => 'student',
            'teacher_id' => 'teacher',
            'primary_teacher_id' => 'primary teacher',
            'grade_levels' => 'grade levels',
            'subject_area' => 'subject area',
            'system_type' => 'system type',
            'grading_system_id' => 'grading system',
            'grade_scale_id' => 'grade scale',
        ];
    }
}
