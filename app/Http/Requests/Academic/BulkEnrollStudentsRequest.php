<?php

namespace App\Http\Requests\Academic;

class BulkEnrollStudentsRequest extends BaseAcademicRequest
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
            'enrollments' => 'required|array|min:1|max:100',
            'enrollments.*.student_id' => 'required|exists:students,id',
            'enrollments.*.class_id' => 'required|exists:classes,id',
            'enrollments.*.enrollment_date' => 'nullable|date|before_or_equal:today',
            'enrollments.*.status' => 'nullable|in:active,inactive,withdrawn',
            'enrollments.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $enrollments = $this->input('enrollments', []);

            // Validate that all students and classes belong to the current school
            foreach ($enrollments as $index => $enrollment) {
                if (isset($enrollment['student_id'])) {
                    $student = \App\Models\V1\SIS\Student\Student::find($enrollment['student_id']);
                    if (!$student || $student->school_id !== $this->getCurrentSchoolId()) {
                        $validator->errors()->add("enrollments.{$index}.student_id", 'Invalid student selected');
                    }
                }

                if (isset($enrollment['class_id'])) {
                    $class = \App\Models\V1\Academic\AcademicClass::find($enrollment['class_id']);
                    if (!$class || $class->school_id !== $this->getCurrentSchoolId()) {
                        $validator->errors()->add("enrollments.{$index}.class_id", 'Invalid class selected');
                    }
                }
            }

            // Check for duplicate enrollments
            $enrollmentKeys = [];
            foreach ($enrollments as $index => $enrollment) {
                $key = $enrollment['student_id'] . '-' . $enrollment['class_id'];
                if (in_array($key, $enrollmentKeys)) {
                    $validator->errors()->add("enrollments.{$index}", 'Duplicate enrollment detected');
                } else {
                    $enrollmentKeys[] = $key;
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
            'enrollments.required' => 'At least one enrollment must be provided',
            'enrollments.max' => 'Cannot enroll more than 100 students at once',
            'enrollments.*.student_id.required' => 'Student is required for each enrollment',
            'enrollments.*.class_id.required' => 'Class is required for each enrollment',
            'enrollments.*.enrollment_date.before_or_equal' => 'Enrollment date cannot be in the future',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'enrollments' => 'enrollments list',
            'enrollments.*.student_id' => 'student',
            'enrollments.*.class_id' => 'class',
            'enrollments.*.enrollment_date' => 'enrollment date',
            'enrollments.*.status' => 'status',
            'enrollments.*.notes' => 'notes',
        ]);
    }
}
