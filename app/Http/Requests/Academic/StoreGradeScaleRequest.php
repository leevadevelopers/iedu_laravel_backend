<?php

namespace App\Http\Requests\Academic;

class StoreGradeScaleRequest extends BaseAcademicRequest
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
            'name' => 'required|string|max:255',
            'scale_type' => 'required|string|in:letter,percentage,points,standards',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'passing_grade' => 'nullable|numeric',
            'status' => 'nullable|in:active,inactive',
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
            // Check for duplicate name within school
            if ($this->filled('name')) {
                $existing = \App\Models\V1\Academic\GradeScale::where('name', $this->name)
                    ->where('school_id', $this->getCurrentSchoolId())
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
            'name' => 'grade scale name',
            'scale_type' => 'scale type',
            'is_default' => 'default status',
        ]);
    }
}
