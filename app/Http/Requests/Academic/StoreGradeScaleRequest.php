<?php

namespace App\Http\Requests\Academic;

class StoreGradeScaleRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'grading_system_id' => 'required|exists:grading_systems,id',
            'name' => 'required|string|max:255',
            'scale_type' => 'required|string|in:letter,numeric,percentage,pass_fail,custom',
            'is_default' => 'nullable|boolean',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that grading system belongs to current school
            if ($this->filled('grading_system_id')) {
                $gradingSystem = \App\Models\V1\Academic\GradingSystem::find($this->grading_system_id);
                if (!$gradingSystem || $gradingSystem->school_id !== $this->getCurrentSchoolId()) {
                    $validator->errors()->add('grading_system_id', 'Invalid grading system selected');
                }
            }

            // Check for duplicate name within grading system
            if ($this->filled('name') && $this->filled('grading_system_id')) {
                $existing = \App\Models\V1\Academic\GradeScale::where('name', $this->name)
                    ->where('grading_system_id', $this->grading_system_id)
                    ->where('school_id', $this->getCurrentSchoolId())
                    ->first();

                if ($existing) {
                    $validator->errors()->add('name', 'Grade scale name already exists in this grading system');
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
            'grading_system_id.required' => 'Grading system is required',
            'grading_system_id.exists' => 'Invalid grading system selected',
            'name.required' => 'Grade scale name is required',
            'scale_type.required' => 'Scale type is required',
            'scale_type.in' => 'Invalid scale type selected',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'grading_system_id' => 'grading system',
            'name' => 'grade scale name',
            'scale_type' => 'scale type',
            'is_default' => 'default status',
        ]);
    }
}
