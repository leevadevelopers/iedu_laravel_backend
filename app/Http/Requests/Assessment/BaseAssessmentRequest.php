<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

abstract class BaseAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the current tenant ID safely, returning null if not available
     */
    protected function getCurrentTenantIdOrNull(): ?int
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return null;
            }

            // Try tenant_id attribute first
            if (isset($user->tenant_id) && $user->tenant_id) {
                return $user->tenant_id;
            }

            // Try getCurrentTenant method
            if (method_exists($user, 'getCurrentTenant')) {
                $currentTenant = $user->getCurrentTenant();
                if ($currentTenant) {
                    return $currentTenant->id;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the current school ID safely, returning null if not available
     */
    protected function getCurrentSchoolIdOrNull(): ?int
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return null;
            }

            // Try school_id attribute
            if (isset($user->school_id) && $user->school_id) {
                return $user->school_id;
            }

            // Try activeSchools relationship
            if (method_exists($user, 'activeSchools')) {
                $schoolUser = $user->activeSchools()->first();
                if ($schoolUser) {
                    return $schoolUser->school_id;
                }
            }

            return null;
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
            'min' => 'The :attribute must be at least :min.',
            'max' => 'The :attribute may not be greater than :max.',
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
            'term_id' => 'term',
            'subject_id' => 'subject',
            'class_id' => 'class',
            'teacher_id' => 'teacher',
            'type_id' => 'type',
            'title' => 'title',
            'description' => 'description',
            'scheduled_date' => 'scheduled date',
            'submission_deadline' => 'submission deadline',
            'total_marks' => 'total marks',
            'weight' => 'weight',
            'status' => 'status',
        ];
    }
}

