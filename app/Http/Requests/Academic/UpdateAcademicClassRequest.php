<?php

namespace App\Http\Requests\Academic;

class UpdateAcademicClassRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        $classId = $this->route('class')->id;

        return [
            'subject_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_year_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:academic_years,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'nullable',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'name' => 'sometimes|required|string|max:255',
            'section' => 'nullable|string|max:10',
            'class_code' => [
                'nullable',
                'string',
                'max:50',
                'unique:classes,class_code,' . $classId . ',id,school_id,' . $this->getCurrentSchoolId()
            ],
            'grade_level' => 'sometimes|required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'max_students' => 'sometimes|required|integer|min:1|max:50',
            'primary_teacher_id' => [
                'nullable',
                'integer',
                'exists:users,id,school_id,' . $this->getCurrentSchoolId() . ',user_type,teacher'
            ],
            'additional_teachers_json' => 'nullable|array',
            'schedule_json' => 'nullable|array',
            'room_number' => 'nullable|string|max:50',
            'status' => 'sometimes|in:planned,active,completed,cancelled'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Cannot reduce max_students below current enrollment
            if ($this->filled('max_students')) {
                $class = $this->route('class');
                if ($class && $this->max_students < $class->current_enrollment) {
                    $validator->errors()->add('max_students',
                        'Cannot reduce maximum students below current enrollment (' . $class->current_enrollment . ').');
                }
            }
        });
    }
}
