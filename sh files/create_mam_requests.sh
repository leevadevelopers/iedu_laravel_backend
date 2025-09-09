#!/bin/bash

# iEDU Academic Management - Request Classes Generation
# Creates all Laravel Form Request classes for validation

echo "🔍 Creating iEDU Academic Management Request Classes..."

# Create Requests directory if not exists
mkdir -p app/Http/Requests/Academic

# Base Academic Request
cat > app/Http/Requests/Academic/BaseAcademicRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\SchoolContextService;

abstract class BaseAcademicRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        // Default authorization - override in child classes if needed
        return auth()->check() && $this->hasValidSchoolContext();
    }

    /**
     * Check if user has valid school context
     */
    protected function hasValidSchoolContext(): bool
    {
        try {
            $schoolContext = app(SchoolContextService::class);
            return $schoolContext->getCurrentSchool() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the current school ID
     */
    protected function getCurrentSchoolId(): int
    {
        return app(SchoolContextService::class)->getCurrentSchool()->id;
    }

    /**
     * Common validation messages
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be a string.',
            'integer' => 'The :attribute must be an integer.',
            'numeric' => 'The :attribute must be a number.',
            'date' => 'The :attribute must be a valid date.',
            'email' => 'The :attribute must be a valid email address.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'in' => 'The selected :attribute is invalid.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'between' => 'The :attribute must be between :min and :max.',
            'array' => 'The :attribute must be an array.',
            'json' => 'The :attribute must be valid JSON.',
        ];
    }

    /**
     * Custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return [
            'school_id' => 'school',
            'academic_year_id' => 'academic year',
            'academic_term_id' => 'academic term',
            'subject_id' => 'subject',
            'class_id' => 'class',
            'student_id' => 'student',
            'teacher_id' => 'teacher',
            'primary_teacher_id' => 'primary teacher',
            'grade_levels' => 'grade levels',
            'subject_area' => 'subject area',
            'system_type' => 'system type',
            'grading_system_id' => 'grading system',
            'grade_scale_id' => 'grade scale',
        ];
    }
}
EOF

# Academic Year Request Classes
cat > app/Http/Requests/Academic/StoreAcademicYearRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

class StoreAcademicYearRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'code' => [
                'required',
                'string',
                'max:20',
                'unique:academic_years,code,NULL,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'start_date' => 'required|date|before:end_date',
            'end_date' => 'required|date|after:start_date',
            'term_structure' => 'required|in:semesters,trimesters,quarters,year_round',
            'total_terms' => 'nullable|integer|min:1|max:4',
            'total_instructional_days' => 'nullable|integer|min:160|max:220',
            'status' => 'nullable|in:planning,active,completed,archived',
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

                // Check minimum duration
                if ($endDate->diffInDays($startDate) < 180) {
                    $validator->errors()->add('end_date', 'Academic year must be at least 180 days long.');
                }

                // Check maximum duration
                if ($endDate->diffInDays($startDate) > 400) {
                    $validator->errors()->add('end_date', 'Academic year cannot exceed 400 days.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default total_terms based on term_structure
        if ($this->filled('term_structure') && !$this->filled('total_terms')) {
            $defaultTerms = [
                'semesters' => 2,
                'trimesters' => 3,
                'quarters' => 4,
                'year_round' => 4
            ];

            $this->merge([
                'total_terms' => $defaultTerms[$this->term_structure] ?? 2
            ]);
        }
    }
}
EOF

cat > app/Http/Requests/Academic/UpdateAcademicYearRequest.php << 'EOF'
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
EOF

# Subject Request Classes
cat > app/Http/Requests/Academic/StoreSubjectRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

class StoreSubjectRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:subjects,code,NULL,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'description' => 'nullable|string|max:1000',
            'subject_area' => [
                'required',
                'in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other'
            ],
            'grade_levels' => 'required|array|min:1',
            'grade_levels.*' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'learning_standards_json' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'integer|exists:subjects,id',
            'credit_hours' => 'nullable|numeric|min:0.5|max:2.0',
            'is_core_subject' => 'nullable|boolean',
            'is_elective' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,archived'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure subject is either core or elective, but not both
            if ($this->boolean('is_core_subject') && $this->boolean('is_elective')) {
                $validator->errors()->add('is_elective', 'A subject cannot be both core and elective.');
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default credit hours based on subject area
        if (!$this->filled('credit_hours')) {
            $defaultCredits = [
                'mathematics' => 1.0,
                'science' => 1.0,
                'language_arts' => 1.0,
                'social_studies' => 1.0,
                'foreign_language' => 1.0,
                'arts' => 0.5,
                'physical_education' => 0.5,
                'technology' => 0.5,
                'vocational' => 1.0,
                'other' => 0.5
            ];

            $this->merge([
                'credit_hours' => $defaultCredits[$this->subject_area] ?? 1.0
            ]);
        }
    }
}
EOF

cat > app/Http/Requests/Academic/UpdateSubjectRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

class UpdateSubjectRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        $subjectId = $this->route('subject')->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'unique:subjects,code,' . $subjectId . ',id,school_id,' . $this->getCurrentSchoolId()
            ],
            'description' => 'nullable|string|max:1000',
            'subject_area' => [
                'sometimes',
                'required',
                'in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other'
            ],
            'grade_levels' => 'sometimes|required|array|min:1',
            'grade_levels.*' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'learning_standards_json' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'integer|exists:subjects,id',
            'credit_hours' => 'nullable|numeric|min:0.5|max:2.0',
            'is_core_subject' => 'nullable|boolean',
            'is_elective' => 'nullable|boolean',
            'status' => 'sometimes|in:active,inactive,archived'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_core_subject') && $this->boolean('is_elective')) {
                $validator->errors()->add('is_elective', 'A subject cannot be both core and elective.');
            }
        });
    }
}
EOF

# Academic Class Request Classes
cat > app/Http/Requests/Academic/StoreAcademicClassRequest.php << 'EOF'
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
                $subject = \App\Models\Academic\Subject::find($this->subject_id);
                if ($subject && !in_array($this->grade_level, $subject->grade_levels ?? [])) {
                    $validator->errors()->add('grade_level', 'The selected subject does not support this grade level.');
                }
            }

            // Validate academic term belongs to academic year
            if ($this->filled('academic_year_id') && $this->filled('academic_term_id')) {
                $term = \App\Models\Academic\AcademicTerm::find($this->academic_term_id);
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
            $subject = \App\Models\Academic\Subject::find($this->subject_id);
            if ($subject) {
                $section = $this->section ?? 'A';
                $classCode = strtoupper($subject->code . '-' . $this->grade_level . '-' . $section);
                $this->merge(['class_code' => $classCode]);
            }
        }
    }
}
EOF

cat > app/Http/Requests/Academic/UpdateAcademicClassRequest.php << 'EOF'
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
EOF

# Grading System Request Classes
cat > app/Http/Requests/Academic/StoreGradingSystemRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

class StoreGradingSystemRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'system_type' => 'required|in:traditional_letter,percentage,points,standards_based,narrative',
            'applicable_grades' => 'nullable|array',
            'applicable_grades.*' => 'string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'applicable_subjects' => 'nullable|array',
            'applicable_subjects.*' => 'string|in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other',
            'is_primary' => 'nullable|boolean',
            'configuration_json' => 'nullable|array',
            'configuration_json.passing_threshold' => 'nullable|numeric|min:0|max:100',
            'configuration_json.gpa_scale' => 'nullable|numeric|min:1|max:10',
            'configuration_json.decimal_places' => 'nullable|integer|min:0|max:3',
            'status' => 'nullable|in:active,inactive'
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default configuration based on system type
        if (!$this->filled('configuration_json')) {
            $defaultConfig = [
                'traditional_letter' => [
                    'passing_threshold' => 60.0,
                    'gpa_scale' => 4.0,
                    'decimal_places' => 2
                ],
                'percentage' => [
                    'passing_threshold' => 60.0,
                    'decimal_places' => 1
                ],
                'points' => [
                    'passing_threshold' => null,
                    'decimal_places' => 0
                ],
                'standards_based' => [
                    'passing_threshold' => 2.0,
                    'gpa_scale' => 4.0,
                    'decimal_places' => 1
                ],
                'narrative' => [
                    'passing_threshold' => null,
                    'decimal_places' => 0
                ]
            ];

            if (isset($defaultConfig[$this->system_type])) {
                $this->merge([
                    'configuration_json' => $defaultConfig[$this->system_type]
                ]);
            }
        }
    }
}
EOF

cat > app/Http/Requests/Academic/UpdateGradingSystemRequest.php << 'EOF'
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
EOF

# Grade Entry Request Classes
cat > app/Http/Requests/Academic/StoreGradeEntryRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

class StoreGradeEntryRequest extends BaseAcademicRequest
{
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
            'assessment_name' => 'required|string|max:255',
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
EOF

cat > app/Http/Requests/Academic/UpdateGradeEntryRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

class UpdateGradeEntryRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'assessment_name' => 'sometimes|required|string|max:255',
            'assessment_type' => 'sometimes|required|in:formative,summative,project,participation,homework,quiz,exam',
            'assessment_date' => 'sometimes|required|date|before_or_equal:today',
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
            // Points earned cannot exceed points possible
            if ($this->filled('points_earned') && $this->filled('points_possible') &&
                $this->points_earned > $this->points_possible) {
                $validator->errors()->add('points_earned', 'Points earned cannot exceed points possible.');
            }
        });
    }
}
EOF

# Bulk Grade Entry Request
cat > app/Http/Requests/Academic/BulkGradeEntryRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Academic;

class BulkGradeEntryRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
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
            'assessment_name' => 'required|string|max:255',
            'assessment_type' => 'required|in:formative,summative,project,participation,homework,quiz,exam',
            'assessment_date' => 'required|date|before_or_equal:today',
            'grade_category' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0|max:10',
            'grades' => 'required|array|min:1',
            'grades.*.student_id' => [
                'required',
                'integer',
                'exists:students,id,school_id,' . $this->getCurrentSchoolId() . ',enrollment_status,enrolled'
            ],
            'grades.*.raw_score' => 'nullable|numeric|min:0',
            'grades.*.percentage_score' => 'nullable|numeric|min:0|max:100',
            'grades.*.letter_grade' => 'nullable|string|max:5',
            'grades.*.points_earned' => 'nullable|numeric|min:0',
            'grades.*.points_possible' => 'nullable|numeric|min:0.1',
            'grades.*.teacher_comments' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('grades')) {
                foreach ($this->grades as $index => $grade) {
                    // At least one score must be provided
                    if (!isset($grade['raw_score']) && !isset($grade['percentage_score']) &&
                        !isset($grade['points_earned']) && !isset($grade['letter_grade'])) {
                        $validator->errors()->add("grades.{$index}.raw_score",
                            'At least one score value must be provided.');
                    }

                    // Points validation
                    if (isset($grade['points_earned']) && isset($grade['points_possible']) &&
                        $grade['points_earned'] > $grade['points_possible']) {
                        $validator->errors()->add("grades.{$index}.points_earned",
                            'Points earned cannot exceed points possible.');
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('weight')) {
            $this->merge(['weight' => 1.0]);
        }
    }
}
EOF

echo "✅ Academic Management Request Classes created successfully!"
echo "📁 Request classes created in: app/Http/Requests/Academic/"
echo "🔧 Next: Create Resources and Routes"
