<?php

namespace App\Http\Requests\Academic;

class StoreGradingSystemRequest extends BaseAcademicRequest
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
            'school_id' => 'required|integer|exists:schools,id',
            'name' => 'required|string|max:255',
            'system_type' => 'required|in:traditional_letter,percentage,points,standards_based,narrative',
            'applicable_grades' => 'nullable|array',
            'applicable_grades.*' => 'string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'applicable_subjects' => 'nullable|array',
            'applicable_subjects.*' => 'string|in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other',
            'is_primary' => 'nullable|boolean',
            'configuration_json' => 'nullable|array',
            'configuration_json.passing_threshold' => 'nullable|numeric|min:0|max:100',
            'configuration_json.gpa_scale' => 'nullable|numeric|min:1|max:10',
            'configuration_json.decimal_places' => 'nullable|integer|min:0|max:3',
            'status' => 'nullable|in:active,inactive'
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set tenant_id automatically (school_id comes from payload)
        $tenantId = $this->getCurrentTenantIdOrNull();

        if (!$tenantId) {
            throw new \Exception('User must have a tenant to create grading systems');
        }

        $this->merge([
            'tenant_id' => $tenantId
        ]);

        // Set default configuration based on system type
        if (!$this->filled('configuration_json')) {
            $defaultConfig = [
                'traditional_letter' => [
                    'passing_threshold' => 60.0,
                    'gpa_scale' => 4.0,
                    'decimal_places' => 2
                ],
                'percentage' => [
                    'passing_threshold' => 60.0,
                    'decimal_places' => 1
                ],
                'points' => [
                    'passing_threshold' => null,
                    'decimal_places' => 0
                ],
                'standards_based' => [
                    'passing_threshold' => 2.0,
                    'gpa_scale' => 4.0,
                    'decimal_places' => 1
                ],
                'narrative' => [
                    'passing_threshold' => null,
                    'decimal_places' => 0
                ]
            ];

            if (isset($defaultConfig[$this->system_type])) {
                $this->merge([
                    'configuration_json' => $defaultConfig[$this->system_type]
                ]);
            }
        }
    }
}
