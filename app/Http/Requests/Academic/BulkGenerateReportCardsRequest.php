<?php

namespace App\Http\Requests\Academic;

use App\Models\V1\SIS\School\AcademicYear;

class BulkGenerateReportCardsRequest extends BaseAcademicRequest
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
            'student_ids' => 'required|array|min:1|max:200',
            'student_ids.*' => 'required|exists:students,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'term' => 'nullable|string|in:first_term,second_term,third_term,annual',
            'format' => 'nullable|string|in:pdf,excel,html',
            'include_comments' => 'nullable|boolean',
            'include_attendance' => 'nullable|boolean',
            'include_behavior' => 'nullable|boolean',
            'template_id' => 'nullable|exists:report_card_templates,id',
            'custom_fields' => 'nullable|array',
            'email_to_parents' => 'nullable|boolean',
            'email_to_students' => 'nullable|boolean',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $studentIds = $this->input('student_ids', []);

            // Validate that all students belong to the current school
            foreach ($studentIds as $index => $studentId) {
                $student = \App\Models\V1\SIS\Student\Student::find($studentId);
                if (!$student || $student->school_id !== $this->getCurrentSchoolId()) {
                    $validator->errors()->add("student_ids.{$index}", 'Invalid student selected');
                }
            }

            // Validate academic year if provided
            if ($this->filled('academic_year_id')) {
                $academicYear = AcademicYear::find($this->academic_year_id);
                if (!$academicYear || $academicYear->school_id !== $this->getCurrentSchoolId()) {
                    $validator->errors()->add('academic_year_id', 'Invalid academic year selected');
                }
            }

            // Validate template if provided (placeholder for future implementation)
            if ($this->filled('template_id')) {
                // TODO: Implement report card template validation when model is created
                $validator->errors()->add('template_id', 'Report card template validation not yet implemented');
            }
        });
    }

    /**
     * Get custom messages for validation errors
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'student_ids.required' => 'At least one student must be selected',
            'student_ids.max' => 'Cannot generate more than 200 report cards at once',
            'student_ids.*.required' => 'Student ID is required',
            'student_ids.*.exists' => 'Invalid student selected',
            'academic_year_id.exists' => 'Invalid academic year selected',
            'term.in' => 'Invalid term selected',
            'format.in' => 'Invalid format selected',
            'template_id.exists' => 'Invalid report card template selected',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'student_ids' => 'students list',
            'student_ids.*' => 'student',
            'academic_year_id' => 'academic year',
            'term' => 'term',
            'format' => 'format',
            'include_comments' => 'include comments',
            'include_attendance' => 'include attendance',
            'include_behavior' => 'include behavior',
            'template_id' => 'report card template',
            'custom_fields' => 'custom fields',
            'email_to_parents' => 'email to parents',
            'email_to_students' => 'email to students',
        ]);
    }
}
