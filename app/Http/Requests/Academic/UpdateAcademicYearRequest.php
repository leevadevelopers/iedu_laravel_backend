<?php

namespace App\Http\Requests\Academic;

class UpdateAcademicYearRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        $academicYearId = $this->route('academic_year')->id ?? $this->route('academicYear')->id;

        return [
            'name' => 'sometimes|required|string|max:100',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'unique:academic_years,code,' . $academicYearId . ',id,school_id,' . $this->getCurrentSchoolId()
            ],
            'start_date' => 'sometimes|required|date|before:end_date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'term_structure' => 'sometimes|required|in:semesters,trimesters,quarters,year_round',
            'total_terms' => 'nullable|integer|min:1|max:4',
            'total_instructional_days' => 'nullable|integer|min:160|max:220',
            'status' => 'sometimes|in:planning,active,completed,archived',
            'is_current' => 'nullable|boolean'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('start_date') && $this->filled('end_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $endDate = \Carbon\Carbon::parse($this->end_date);

                if ($endDate->diffInDays($startDate) < 180) {
                    $validator->errors()->add('end_date', 'Academic year must be at least 180 days long.');
                }

                if ($endDate->diffInDays($startDate) > 400) {
                    $validator->errors()->add('end_date', 'Academic year cannot exceed 400 days.');
                }
            }
        });
    }
}
