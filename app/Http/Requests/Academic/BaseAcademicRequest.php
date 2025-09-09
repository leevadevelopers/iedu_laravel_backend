<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\SchoolContextService;

abstract class BaseAcademicRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        // Default authorization - override in child classes if needed
        return auth()->check() && $this->hasValidSchoolContext();
    }

    /**
     * Check if user has valid school context
     */
    protected function hasValidSchoolContext(): bool
    {
        try {
            $schoolContext = app(SchoolContextService::class);
            return $schoolContext->hasSchoolContext();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the current school ID
     */
    protected function getCurrentSchoolId(): int
    {
        return app(SchoolContextService::class)->getCurrentSchoolId();
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
