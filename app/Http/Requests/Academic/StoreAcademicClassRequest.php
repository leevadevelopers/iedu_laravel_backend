<?php

namespace App\Http\Requests\Academic;

class StoreAcademicClassRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_year_id' => [
                'required',
                'integer',
                'exists:academic_years,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'nullable',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'name' => 'required|string|max:255',
            'section' => 'nullable|string|max:10',
            'class_code' => [
                'nullable',
                'string',
                'max:50',
                'unique:classes,class_code,NULL,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'grade_level' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'max_students' => 'required|integer|min:1|max:50',
            'primary_teacher_id' => [
                'nullable',
                'integer',
                'exists:users,id,school_id,' . $this->getCurrentSchoolId() . ',user_type,teacher'
            ],
            'additional_teachers_json' => 'nullable|array',
            'additional_teachers_json.*.teacher_id' => [
                'required',
                'integer',
                'exists:users,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'additional_teachers_json.*.role' => 'required|string|in:co-teacher,assistant,substitute',
            'schedule_json' => 'nullable|array',
            'schedule_json.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedule_json.*.start_time' => 'required|date_format:H:i',
            'schedule_json.*.end_time' => 'required|date_format:H:i|after:schedule_json.*.start_time',
            'schedule_json.*.room' => 'nullable|string|max:50',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:planned,active,completed,cancelled'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that subject supports the grade level
            if ($this->filled('subject_id') && $this->filled('grade_level')) {
                $subject = \App\Models\V1\Academic\Subject::find($this->subject_id);
                if ($subject && !in_array($this->grade_level, $subject->grade_levels ?? [])) {
                    $validator->errors()->add('grade_level', 'The selected subject does not support this grade level.');
                }
            }

            // Validate academic term belongs to academic year
            if ($this->filled('academic_year_id') && $this->filled('academic_term_id')) {
                $term = \App\Models\V1\SIS\School\AcademicTerm::find($this->academic_term_id);
                if ($term && $term->academic_year_id != $this->academic_year_id) {
                    $validator->errors()->add('academic_term_id', 'The selected term does not belong to the specified academic year.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Generate class code if not provided
        if (!$this->filled('class_code') && $this->filled('subject_id') && $this->filled('grade_level')) {
            $subject = \App\Models\V1\Academic\Subject::find($this->subject_id);
            if ($subject) {
                $section = $this->section ?? 'A';
                $classCode = strtoupper($subject->code . '-' . $this->grade_level . '-' . $section);
                $this->merge(['class_code' => $classCode]);
            }
        }
    }
}
