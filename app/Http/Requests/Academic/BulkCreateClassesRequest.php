<?php

namespace App\Http\Requests\Academic;

class BulkCreateClassesRequest extends BaseAcademicRequest
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
        $schoolId = $this->getCurrentSchoolIdOrNull();

        return [
            'classes' => 'required|array|min:1|max:50',
            'classes.*.school_id' => 'required|integer|exists:schools,id',
            'classes.*.subject_id' => [
                'required',
                'integer',
                'exists:subjects,id,school_id,' . ($schoolId ?? 'NULL')
            ],
            'classes.*.academic_year_id' => [
                'required',
                'integer',
                'exists:academic_years,id,school_id,' . ($schoolId ?? 'NULL')
            ],
            'classes.*.academic_term_id' => [
                'nullable',
                'integer',
                'exists:academic_terms,id,school_id,' . ($schoolId ?? 'NULL')
            ],
            'classes.*.name' => 'required|string|max:255',
            'classes.*.section' => 'nullable|string|max:10',
            'classes.*.class_code' => [
                'nullable',
                'string',
                'max:50'
            ],
            'classes.*.grade_level' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'classes.*.max_students' => 'required|integer|min:1|max:50',
            'classes.*.primary_teacher_id' => [
                'nullable',
                'integer',
                'exists:teachers,id,school_id,' . ($schoolId ?? 'NULL') . ',status,active'
            ],
            'classes.*.additional_teachers_json' => 'nullable|array',
            'classes.*.additional_teachers_json.*.teacher_id' => [
                'required',
                'integer',
                'exists:teachers,id,school_id,' . ($schoolId ?? 'NULL') . ',status,active'
            ],
            'classes.*.additional_teachers_json.*.role' => 'required|string|in:co-teacher,assistant,substitute',
            'classes.*.schedule_json' => 'nullable|array',
            'classes.*.schedule_json.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'classes.*.schedule_json.*.start_time' => 'required|date_format:H:i',
            'classes.*.schedule_json.*.end_time' => 'required|date_format:H:i|after:classes.*.schedule_json.*.start_time',
            'classes.*.schedule_json.*.room' => 'nullable|string|max:50',
            'classes.*.room_number' => 'nullable|string|max:50',
            'classes.*.status' => 'nullable|in:planned,active,completed,cancelled',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $classes = $this->input('classes', []);

            foreach ($classes as $index => $class) {
                // Validate that subject supports the grade level
                if (isset($class['subject_id']) && isset($class['grade_level'])) {
                    $subject = \App\Models\V1\Academic\Subject::find($class['subject_id']);
                    if ($subject && !in_array($class['grade_level'], $subject->grade_levels ?? [])) {
                        $validator->errors()->add("classes.{$index}.grade_level", 'The selected subject does not support this grade level.');
                    }
                }

                // Validate academic term belongs to academic year
                if (isset($class['academic_year_id']) && isset($class['academic_term_id'])) {
                    $term = \App\Models\V1\SIS\School\AcademicTerm::find($class['academic_term_id']);
                    if ($term && $term->academic_year_id != $class['academic_year_id']) {
                        $validator->errors()->add("classes.{$index}.academic_term_id", 'The selected term does not belong to the specified academic year.');
                    }
                }

                // Validate that all subjects belong to the current school
                if (isset($class['subject_id'])) {
                    $subject = \App\Models\V1\Academic\Subject::find($class['subject_id']);
                    $schoolId = $this->getCurrentSchoolIdOrNull();
                    if (!$subject || ($schoolId && $subject->school_id !== $schoolId)) {
                        $validator->errors()->add("classes.{$index}.subject_id", 'Invalid subject selected');
                    }
                }

                // Validate that teacher belongs to the current school
                if (isset($class['primary_teacher_id'])) {
                    $teacher = \App\Models\V1\Academic\Teacher::find($class['primary_teacher_id']);
                    $schoolId = $this->getCurrentSchoolIdOrNull();
                    if (!$teacher || ($schoolId && $teacher->school_id !== $schoolId)) {
                        $validator->errors()->add("classes.{$index}.primary_teacher_id", 'Invalid teacher selected');
                    }
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
            'classes.required' => 'At least one class must be provided',
            'classes.max' => 'Cannot create more than 50 classes at once',
            'classes.*.name.required' => 'Class name is required',
            'classes.*.subject_id.required' => 'Subject is required for each class',
            'classes.*.grade_level.required' => 'Grade level is required for each class',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'classes' => 'classes list',
            'classes.*.name' => 'class name',
            'classes.*.subject_id' => 'subject',
            'classes.*.primary_teacher_id' => 'primary teacher',
            'classes.*.grade_level' => 'grade level',
            'classes.*.section' => 'section',
            'classes.*.class_code' => 'class code',
            'classes.*.max_students' => 'maximum students',
            'classes.*.room_number' => 'room number',
            'classes.*.schedule_json' => 'schedule',
            'classes.*.academic_year_id' => 'academic year',
            'classes.*.academic_term_id' => 'academic term',
            'classes.*.status' => 'status',
            'classes.*.additional_teachers_json' => 'additional teachers',
        ]);
    }
}
