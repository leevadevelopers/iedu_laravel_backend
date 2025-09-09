<?php

namespace App\Http\Requests\Academic;

class UpdateGradingSystemRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'system_type' => 'sometimes|required|in:traditional_letter,percentage,points,standards_based,narrative',
            'applicable_grades' => 'nullable|array',
            'applicable_grades.*' => 'string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'applicable_subjects' => 'nullable|array',
            'applicable_subjects.*' => 'string|in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other',
            'is_primary' => 'nullable|boolean',
            'configuration_json' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive'
        ];
    }
}
