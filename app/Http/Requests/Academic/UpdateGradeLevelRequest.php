<?php

namespace App\Http\Requests\Academic;

class UpdateGradeLevelRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'grade_scale_id' => 'nullable|exists:grade_scales,id',
            'grade_value' => 'nullable|string|max:50',
            'display_value' => 'nullable|string|max:50',
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
            $gradeLevel = $this->route('gradeLevel');
            if (!$gradeLevel) {
                return;
            }

            // Validate that grade scale belongs to current school
            if ($this->filled('grade_scale_id')) {
                $gradeScale = \App\Models\V1\Academic\GradeScale::find($this->grade_scale_id);
                if (!$gradeScale || $gradeScale->school_id !== $this->getCurrentSchoolId()) {
                    $validator->errors()->add('grade_scale_id', 'Invalid grade scale selected');
                }
            }

            // Validate percentage range
            $percentageMin = $this->input('percentage_min', $gradeLevel->percentage_min);
            $percentageMax = $this->input('percentage_max', $gradeLevel->percentage_max);

            if ($percentageMin !== null && $percentageMax !== null) {
                if ($percentageMin >= $percentageMax) {
                    $validator->errors()->add('percentage_max', 'Maximum percentage must be greater than minimum percentage');
                }
            }

            // Check for overlapping percentage ranges
            $gradeScaleId = $this->input('grade_scale_id', $gradeLevel->grade_scale_id);
            if ($this->filled('grade_scale_id') && $percentageMin !== null && $percentageMax !== null) {
                $overlapping = \App\Models\V1\Academic\GradeLevel::where('grade_scale_id', $gradeScaleId)
                    ->where('id', '!=', $gradeLevel->id)
                    ->where(function ($q) use ($percentageMin, $percentageMax) {
                        $q->whereBetween('percentage_min', [$percentageMin, $percentageMax])
                          ->orWhereBetween('percentage_max', [$percentageMin, $percentageMax])
                          ->orWhere(function ($q2) use ($percentageMin, $percentageMax) {
                              $q2->where('percentage_min', '<=', $percentageMin)
                                 ->where('percentage_max', '>=', $percentageMax);
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
            'grade_scale_id.exists' => 'Invalid grade scale selected',
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
