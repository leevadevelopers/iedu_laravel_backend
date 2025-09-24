<?php

namespace App\Http\Requests\Academic;

class StoreGradeLevelRequest extends BaseAcademicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'grade_scale_id' => 'required|exists:grade_scales,id',
            'grade_value' => 'required|string|max:50',
            'display_value' => 'required|string|max:50',
            'numeric_value' => 'nullable|numeric|min:0|max:100',
            'gpa_points' => 'nullable|numeric|min:0|max:4',
            'percentage_min' => 'nullable|numeric|min:0|max:100',
            'percentage_max' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
            'color_code' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_passing' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that grade scale belongs to current school
            if ($this->filled('grade_scale_id')) {
                $gradeScale = \App\Models\V1\Academic\GradeScale::find($this->grade_scale_id);
                if (!$gradeScale || $gradeScale->school_id !== $this->getCurrentSchoolId()) {
                    $validator->errors()->add('grade_scale_id', 'Invalid grade scale selected');
                }
            }

            // Validate percentage range
            if ($this->filled('percentage_min') && $this->filled('percentage_max')) {
                if ($this->percentage_min >= $this->percentage_max) {
                    $validator->errors()->add('percentage_max', 'Maximum percentage must be greater than minimum percentage');
                }
            }

            // Check for overlapping percentage ranges
            if ($this->filled('grade_scale_id') && $this->filled('percentage_min') && $this->filled('percentage_max')) {
                $overlapping = \App\Models\V1\Academic\GradeLevel::where('grade_scale_id', $this->grade_scale_id)
                    ->where(function ($q) {
                        $q->whereBetween('percentage_min', [$this->percentage_min, $this->percentage_max])
                          ->orWhereBetween('percentage_max', [$this->percentage_min, $this->percentage_max])
                          ->orWhere(function ($q2) {
                              $q2->where('percentage_min', '<=', $this->percentage_min)
                                 ->where('percentage_max', '>=', $this->percentage_max);
                          });
                    })
                    ->first();

                if ($overlapping) {
                    $validator->errors()->add('percentage_range', 'Percentage range overlaps with existing grade level');
                }
            }
        });
    }

    /**
     * Get custom messages for validation errors
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'grade_scale_id.required' => 'Grade scale is required',
            'grade_scale_id.exists' => 'Invalid grade scale selected',
            'grade_value.required' => 'Grade value is required',
            'display_value.required' => 'Display value is required',
            'numeric_value.max' => 'Numeric value cannot exceed 100',
            'gpa_points.max' => 'GPA points cannot exceed 4',
            'percentage_min.max' => 'Minimum percentage cannot exceed 100',
            'percentage_max.max' => 'Maximum percentage cannot exceed 100',
            'color_code.regex' => 'Color code must be a valid hex color (e.g., #FF0000)',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'grade_scale_id' => 'grade scale',
            'grade_value' => 'grade value',
            'display_value' => 'display value',
            'numeric_value' => 'numeric value',
            'gpa_points' => 'GPA points',
            'percentage_min' => 'minimum percentage',
            'percentage_max' => 'maximum percentage',
            'description' => 'description',
            'color_code' => 'color code',
            'is_passing' => 'passing status',
            'sort_order' => 'sort order',
        ]);
    }
}
