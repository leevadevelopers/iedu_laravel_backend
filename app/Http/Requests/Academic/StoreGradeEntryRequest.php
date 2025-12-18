<?php

namespace App\Http\Requests\Academic;

class StoreGradeEntryRequest extends BaseAcademicRequest
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
            'student_id' => [
                'required',
                'integer',
                'exists:students,id,school_id,' . $this->getCurrentSchoolId() . ',enrollment_status,enrolled'
            ],
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'required',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'assessment_id' => [
                'nullable',
                'integer',
                'exists:assessments,id'
            ],
            'assessment_name' => 'required_without:assessment_id|string|max:255',
            'assessment_type' => 'required|in:formative,summative,project,participation,homework,quiz,exam',
            'assessment_date' => 'required|date|before_or_equal:today',
            'raw_score' => 'nullable|numeric|min:0',
            'percentage_score' => 'nullable|numeric|min:0|max:100',
            'letter_grade' => 'nullable|string|max:5',
            'points_earned' => 'nullable|numeric|min:0',
            'points_possible' => 'nullable|numeric|min:0.1',
            'grade_category' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0|max:10',
            'teacher_comments' => 'nullable|string|max:1000',
            'private_notes' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Either assessment_id or assessment_name must be provided
            if (!$this->filled('assessment_id') && !$this->filled('assessment_name')) {
                $validator->errors()->add('assessment_id', 'Either assessment_id or assessment_name must be provided.');
            }

            // If assessment_id is provided, validate it exists and optionally auto-fill assessment_name
            if ($this->filled('assessment_id')) {
                $assessment = \App\Models\Assessment\Assessment::find($this->assessment_id);
                if ($assessment && !$this->filled('assessment_name')) {
                    // Auto-fill assessment_name from assessment if not provided
                    $this->merge(['assessment_name' => $assessment->title]);
                }
            }

            // At least one score type must be provided
            if (!$this->filled('raw_score') && !$this->filled('percentage_score') &&
                !$this->filled('points_earned') && !$this->filled('letter_grade')) {
                $validator->errors()->add('raw_score', 'At least one score value must be provided.');
            }

            // If points are used, both earned and possible must be provided
            if ($this->filled('points_earned') && !$this->filled('points_possible')) {
                $validator->errors()->add('points_possible', 'Points possible is required when points earned is provided.');
            }

            // Points earned cannot exceed points possible
            if ($this->filled('points_earned') && $this->filled('points_possible') &&
                $this->points_earned > $this->points_possible) {
                $validator->errors()->add('points_earned', 'Points earned cannot exceed points possible.');
            }

            // Validate student is enrolled in the class
            if ($this->filled('student_id') && $this->filled('class_id')) {
                $enrollment = \DB::table('student_class_enrollments')
                    ->where('student_id', $this->student_id)
                    ->where('class_id', $this->class_id)
                    ->where('status', 'active')
                    ->exists();

                if (!$enrollment) {
                    $validator->errors()->add('student_id', 'Student is not enrolled in the selected class.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default weight if not provided
        if (!$this->filled('weight')) {
            $this->merge(['weight' => 1.0]);
        }

        // Set default grade category based on assessment type
        if (!$this->filled('grade_category') && $this->filled('assessment_type')) {
            $categories = [
                'formative' => 'Formative Assessment',
                'summative' => 'Summative Assessment',
                'project' => 'Projects',
                'participation' => 'Participation',
                'homework' => 'Homework',
                'quiz' => 'Quizzes',
                'exam' => 'Exams'
            ];

            $this->merge([
                'grade_category' => $categories[$this->assessment_type] ?? 'Other'
            ]);
        }
    }
}
