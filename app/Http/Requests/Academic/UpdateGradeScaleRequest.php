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
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'scale_type' => 'nullable|string|in:letter,percentage,points,standards',
            'min_value' => 'nullable|numeric|min:0',
            'max_value' => 'nullable|numeric|min:0',
            'passing_grade' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,inactive',
            'is_default' => 'nullable|boolean',
            'configuration_json' => 'nullable|array',
            'configuration_json.passing_threshold' => 'nullable|numeric|min:0|max:100',
            'configuration_json.gpa_scale' => 'nullable|numeric|min:1|max:10',
            'configuration_json.decimal_places' => 'nullable|integer|min:0|max:3',
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

            // Get the grade scale
            $gradeScale = \App\Models\V1\Academic\GradeScale::where('id', $gradeScaleId)
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if (!$gradeScale) {
                $validator->errors()->add('gradeScale', 'Grade scale not found');
                return;
            }

            // Check for duplicate name within school
            if ($this->filled('name')) {
                $existing = \App\Models\V1\Academic\GradeScale::where('name', $this->name)
                    ->where('school_id', $this->getCurrentSchoolId())
                    ->where('id', '!=', $gradeScale->id)
                    ->first();

                if ($existing) {
                    $validator->errors()->add('name', 'Grade scale name already exists in this school');
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
            'scale_type.in' => 'Invalid scale type selected',
            'status.in' => 'Invalid status selected',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'name' => 'grade scale name',
            'code' => 'code',
            'description' => 'description',
            'scale_type' => 'scale type',
            'min_value' => 'minimum value',
            'max_value' => 'maximum value',
            'passing_grade' => 'passing grade',
            'status' => 'status',
            'is_default' => 'default status',
            'configuration_json' => 'configuration',
        ]);
    }
}
