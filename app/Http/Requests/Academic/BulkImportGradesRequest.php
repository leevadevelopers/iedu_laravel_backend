<?php

namespace App\Http\Requests\Academic;

class BulkImportGradesRequest extends BaseAcademicRequest
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
            'grades' => 'required|array|min:1|max:500',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.academic_class_id' => 'required|exists:academic_classes,id',
            'grades.*.subject_id' => 'required|exists:subjects,id',
            'grades.*.grade_level_id' => 'nullable|exists:grade_levels,id',
            'grades.*.percentage' => 'nullable|numeric|min:0|max:100',
            'grades.*.gpa_points' => 'nullable|numeric|min:0|max:4',
            'grades.*.term' => 'required|string|in:first_term,second_term,third_term,annual',
            'grades.*.academic_year_id' => 'nullable|exists:academic_years,id',
            'grades.*.assignment_name' => 'nullable|string|max:255',
            'grades.*.assignment_type' => 'nullable|string|in:homework,quiz,test,project,participation,exam,other',
            'grades.*.max_points' => 'nullable|numeric|min:0|max:10000',
            'grades.*.earned_points' => 'nullable|numeric|min:0',
            'grades.*.comments' => 'nullable|string|max:1000',
            'grades.*.graded_date' => 'nullable|date|before_or_equal:today',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $grades = $this->input('grades', []);

            // Validate that all related entities belong to the current school
            foreach ($grades as $index => $grade) {
                if (isset($grade['student_id'])) {
                    $student = \App\Models\V1\SIS\Student\Student::find($grade['student_id']);
                    if (!$student || $student->school_id !== $this->getCurrentSchoolId()) {
                        $validator->errors()->add("grades.{$index}.student_id", 'Invalid student selected');
                    }
                }

                if (isset($grade['academic_class_id'])) {
                    $class = \App\Models\V1\Academic\AcademicClass::find($grade['academic_class_id']);
                    if (!$class || $class->school_id !== $this->getCurrentSchoolId()) {
                        $validator->errors()->add("grades.{$index}.academic_class_id", 'Invalid class selected');
                    }
                }

                if (isset($grade['subject_id'])) {
                    $subject = \App\Models\V1\Academic\Subject::find($grade['subject_id']);
                    if (!$subject || $subject->school_id !== $this->getCurrentSchoolId()) {
                        $validator->errors()->add("grades.{$index}.subject_id", 'Invalid subject selected');
                    }
                }

                if (isset($grade['grade_level_id'])) {
                    $gradeLevel = \App\Models\V1\Academic\GradeLevel::find($grade['grade_level_id']);
                    if (!$gradeLevel || $gradeLevel->gradeScale->school_id !== $this->getCurrentSchoolId()) {
                        $validator->errors()->add("grades.{$index}.grade_level_id", 'Invalid grade level selected');
                    }
                }

                // Validate earned_points vs max_points
                if (isset($grade['earned_points']) && isset($grade['max_points'])) {
                    if ($grade['earned_points'] > $grade['max_points']) {
                        $validator->errors()->add("grades.{$index}.earned_points", 'Earned points cannot exceed maximum points');
                    }
                }

                // Validate percentage vs earned_points/max_points
                if (isset($grade['percentage']) && isset($grade['earned_points']) && isset($grade['max_points'])) {
                    $calculatedPercentage = ($grade['earned_points'] / $grade['max_points']) * 100;
                    if (abs($grade['percentage'] - $calculatedPercentage) > 0.01) {
                        $validator->errors()->add("grades.{$index}.percentage", 'Percentage does not match earned points and maximum points');
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
            'grades.required' => 'At least one grade must be provided',
            'grades.max' => 'Cannot import more than 500 grades at once',
            'grades.*.student_id.required' => 'Student is required for each grade',
            'grades.*.academic_class_id.required' => 'Class is required for each grade',
            'grades.*.subject_id.required' => 'Subject is required for each grade',
            'grades.*.term.required' => 'Term is required for each grade',
            'grades.*.percentage.max' => 'Percentage cannot exceed 100',
            'grades.*.gpa_points.max' => 'GPA points cannot exceed 4',
            'grades.*.graded_date.before_or_equal' => 'Graded date cannot be in the future',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'grades' => 'grades list',
            'grades.*.student_id' => 'student',
            'grades.*.academic_class_id' => 'class',
            'grades.*.subject_id' => 'subject',
            'grades.*.grade_level_id' => 'grade level',
            'grades.*.percentage' => 'percentage',
            'grades.*.gpa_points' => 'GPA points',
            'grades.*.term' => 'term',
            'grades.*.academic_year_id' => 'academic year',
            'grades.*.assignment_name' => 'assignment name',
            'grades.*.assignment_type' => 'assignment type',
            'grades.*.max_points' => 'maximum points',
            'grades.*.earned_points' => 'earned points',
            'grades.*.comments' => 'comments',
            'grades.*.graded_date' => 'graded date',
        ]);
    }
}
