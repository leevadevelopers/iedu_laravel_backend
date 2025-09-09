<?php

namespace App\Http\Requests\Academic;

class BulkCreateClassesRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'classes' => 'required|array|min:1|max:50',
            'classes.*.name' => 'required|string|max:255',
            'classes.*.subject_id' => 'required|exists:subjects,id',
            'classes.*.primary_teacher_id' => 'nullable|exists:teachers,id',
            'classes.*.grade_level' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'classes.*.section' => 'nullable|string|max:50',
            'classes.*.room' => 'nullable|string|max:100',
            'classes.*.max_enrollment' => 'nullable|integer|min:1|max:1000',
            'classes.*.schedule_json' => 'nullable|array',
            'classes.*.academic_year_id' => 'nullable|exists:academic_years,id',
            'classes.*.term' => 'nullable|string|in:first_term,second_term,third_term,annual',
            'classes.*.status' => 'nullable|in:active,inactive,archived',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $classes = $this->input('classes', []);

            // Validate that all subjects belong to the current school
            foreach ($classes as $index => $class) {
                if (isset($class['subject_id'])) {
                    $subject = \App\Models\V1\Academic\Subject::find($class['subject_id']);
                    if (!$subject || $subject->school_id !== $this->getCurrentSchoolId()) {
                        $validator->errors()->add("classes.{$index}.subject_id", 'Invalid subject selected');
                    }
                }

                // Validate that teacher belongs to the current school
                if (isset($class['primary_teacher_id'])) {
                    $teacher = \App\Models\V1\Academic\Teacher::find($class['primary_teacher_id']);
                    if (!$teacher || $teacher->school_id !== $this->getCurrentSchoolId()) {
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
            'classes.*.room' => 'room',
            'classes.*.max_enrollment' => 'maximum enrollment',
            'classes.*.schedule_json' => 'schedule',
            'classes.*.academic_year_id' => 'academic year',
            'classes.*.term' => 'term',
            'classes.*.status' => 'status',
        ]);
    }
}
