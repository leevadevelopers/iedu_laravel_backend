<?php

namespace App\Http\Requests\Academic;

class UpdateGradeScaleRequest extends BaseAcademicRequest
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
            'grading_system_id' => 'nullable|exists:grading_systems,id',
            'name' => 'nullable|string|max:255',
            'scale_type' => 'nullable|string|in:letter,percentage,points,standards',
            'is_default' => 'nullable|boolean',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $gradeScaleId = $this->route('gradeScale');
            if (!$gradeScaleId) {
                return;
            }

            // Get the grade scale to access its current grading_system_id
            $gradeScale = \App\Models\V1\Academic\GradeScale::where('id', $gradeScaleId)
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if (!$gradeScale) {
                $validator->errors()->add('gradeScale', 'Grade scale not found');
                return;
            }

            // Validate that grading system belongs to current school
            if ($this->filled('grading_system_id')) {
                $gradingSystem = \App\Models\V1\Academic\GradingSystem::find($this->grading_system_id);
                if (!$gradingSystem || $gradingSystem->school_id !== $this->getCurrentSchoolId()) {
                    $validator->errors()->add('grading_system_id', 'Invalid grading system selected');
                }
            }

            // Check for duplicate name within grading system
            if ($this->filled('name')) {
                $gradingSystemId = $this->input('grading_system_id', $gradeScale->grading_system_id);
                $existing = \App\Models\V1\Academic\GradeScale::where('name', $this->name)
                    ->where('grading_system_id', $gradingSystemId)
                    ->where('school_id', $this->getCurrentSchoolId())
                    ->where('id', '!=', $gradeScale->id)
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
            'grading_system_id.exists' => 'Invalid grading system selected',
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
