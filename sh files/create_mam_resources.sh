#!/bin/bash

# iEDU Academic Management - API Resources Generation
# Creates all Laravel API Resource classes for data transformation

echo "🎨 Creating iEDU Academic Management API Resources..."

# Create Resources directory if not exists
mkdir -p app/Http/Resources/Academic

# Base Academic Resource
cat > app/Http/Resources/Academic/BaseAcademicResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseAcademicResource extends JsonResource
{
    /**
     * Format datetime for API response
     */
    protected function formatDateTime($datetime): ?string
    {
        return $datetime ? $datetime->format('Y-m-d H:i:s') : null;
    }

    /**
     * Format date for API response
     */
    protected function formatDate($date): ?string
    {
        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * Format time for API response
     */
    protected function formatTime($time): ?string
    {
        return $time ? $time->format('H:i:s') : null;
    }

    /**
     * Format decimal for API response
     */
    protected function formatDecimal($value, int $decimals = 2): ?float
    {
        return $value !== null ? round((float) $value, $decimals) : null;
    }

    /**
     * Check if a relation should be loaded
     */
    protected function whenLoaded(string $relation, $value = null)
    {
        return $this->resource->relationLoaded($relation) ?
            ($value ?? $this->resource->{$relation}) :
            $this->missingValue();
    }

    /**
     * Add common metadata to resource
     */
    protected function addMetadata(array $data): array
    {
        return array_merge($data, [
            'meta' => [
                'created_at' => $this->formatDateTime($this->created_at),
                'updated_at' => $this->formatDateTime($this->updated_at),
                'school_id' => $this->school_id,
            ]
        ]);
    }
}
EOF

# Academic Year Resource
cat > app/Http/Resources/Academic/AcademicYearResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class AcademicYearResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'term_structure' => $this->term_structure,
            'total_terms' => $this->total_terms,
            'total_instructional_days' => $this->total_instructional_days,
            'status' => $this->status,
            'is_current' => $this->is_current,
            'duration_days' => $this->getDurationInDays(),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'terms' => $this->whenLoaded('terms', AcademicTermResource::collection($this->terms)),
            'classes' => $this->whenLoaded('classes', AcademicClassResource::collection($this->classes)),
            'students' => $this->whenLoaded('students', StudentResource::collection($this->students)),

            // Statistics (when needed)
            'stats' => $this->when(
                $this->resource->relationLoaded('terms'),
                function () {
                    return [
                        'terms_count' => $this->terms->count(),
                        'active_terms_count' => $this->terms->where('status', 'active')->count(),
                    ];
                }
            ),
        ]);
    }
}
EOF

# Academic Term Resource
cat > app/Http/Resources/Academic/AcademicTermResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class AcademicTermResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'term_number' => $this->term_number,
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'instructional_days' => $this->instructional_days,
            'status' => $this->status,
            'is_current' => $this->isCurrent(),
            'duration_days' => $this->getDurationInDays(),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'academic_year' => $this->whenLoaded('academicYear', new AcademicYearResource($this->academicYear)),
            'classes' => $this->whenLoaded('classes', AcademicClassResource::collection($this->classes)),
            'grade_entries' => $this->whenLoaded('gradeEntries', GradeEntryResource::collection($this->gradeEntries)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('classes'),
                function () {
                    return [
                        'classes_count' => $this->classes->count(),
                        'active_classes_count' => $this->classes->where('status', 'active')->count(),
                    ];
                }
            ),
        ]);
    }
}
EOF

# Subject Resource
cat > app/Http/Resources/Academic/SubjectResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class SubjectResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'subject_area' => $this->subject_area,
            'grade_levels' => $this->grade_levels,
            'learning_standards_json' => $this->learning_standards_json,
            'prerequisites' => $this->prerequisites,
            'credit_hours' => $this->formatDecimal($this->credit_hours, 1),
            'is_core_subject' => $this->is_core_subject,
            'is_elective' => $this->is_elective,
            'status' => $this->status,

            // Helper fields
            'display_name' => $this->name . ' (' . $this->code . ')',
            'subject_type' => $this->is_core_subject ? 'core' : ($this->is_elective ? 'elective' : 'regular'),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'classes' => $this->whenLoaded('classes', AcademicClassResource::collection($this->classes)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('classes') || isset($this->classes_count),
                function () {
                    return [
                        'classes_count' => $this->classes_count ?? $this->classes->count(),
                        'active_classes_count' => $this->classes_count ?? $this->classes->where('status', 'active')->count(),
                        'grade_levels_count' => count($this->grade_levels ?? []),
                    ];
                }
            ),
        ]);
    }
}
EOF

# Academic Class Resource
cat > app/Http/Resources/Academic/AcademicClassResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class AcademicClassResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_term_id' => $this->academic_term_id,
            'name' => $this->name,
            'section' => $this->section,
            'class_code' => $this->class_code,
            'grade_level' => $this->grade_level,
            'max_students' => $this->max_students,
            'current_enrollment' => $this->current_enrollment,
            'primary_teacher_id' => $this->primary_teacher_id,
            'additional_teachers_json' => $this->additional_teachers_json,
            'schedule_json' => $this->schedule_json,
            'room_number' => $this->room_number,
            'status' => $this->status,

            // Calculated fields
            'enrollment_percentage' => $this->formatDecimal($this->getEnrollmentPercentage()),
            'available_seats' => $this->getAvailableSeats(),
            'has_available_seats' => $this->hasAvailableSeats(),
            'display_name' => $this->name . ($this->section ? ' - ' . $this->section : ''),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'subject' => $this->whenLoaded('subject', new SubjectResource($this->subject)),
            'academic_year' => $this->whenLoaded('academicYear', new AcademicYearResource($this->academicYear)),
            'academic_term' => $this->whenLoaded('academicTerm', new AcademicTermResource($this->academicTerm)),
            'primary_teacher' => $this->whenLoaded('primaryTeacher', new TeacherResource($this->primaryTeacher)),
            'students' => $this->whenLoaded('students', StudentResource::collection($this->students)),
            'grade_entries' => $this->whenLoaded('gradeEntries', GradeEntryResource::collection($this->gradeEntries)),

            // Schedule information
            'schedule' => $this->when(
                $this->schedule_json,
                function () {
                    return collect($this->schedule_json)->map(function ($schedule) {
                        return [
                            'day' => $schedule['day'] ?? null,
                            'start_time' => $schedule['start_time'] ?? null,
                            'end_time' => $schedule['end_time'] ?? null,
                            'room' => $schedule['room'] ?? $this->room_number,
                        ];
                    });
                }
            ),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('gradeEntries') || $this->resource->relationLoaded('students'),
                function () {
                    $stats = [];

                    if ($this->resource->relationLoaded('gradeEntries')) {
                        $gradeEntries = $this->gradeEntries;
                        $stats['grade_entries_count'] = $gradeEntries->count();
                        $stats['average_grade'] = $gradeEntries->isNotEmpty()
                            ? $this->formatDecimal($gradeEntries->avg('percentage_score'))
                            : null;
                    }

                    if ($this->resource->relationLoaded('students')) {
                        $stats['enrolled_students_count'] = $this->students->count();
                    }

                    return $stats;
                }
            ),
        ]);
    }
}
EOF

# Grading System Resource
cat > app/Http/Resources/Academic/GradingSystemResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradingSystemResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'name' => $this->name,
            'system_type' => $this->system_type,
            'applicable_grades' => $this->applicable_grades,
            'applicable_subjects' => $this->applicable_subjects,
            'is_primary' => $this->is_primary,
            'configuration_json' => $this->configuration_json,
            'status' => $this->status,

            // Helper fields
            'display_name' => $this->name . ($this->is_primary ? ' (Primary)' : ''),
            'type_label' => $this->getSystemTypeLabel(),

            // Configuration helpers
            'passing_threshold' => $this->configuration_json['passing_threshold'] ?? null,
            'gpa_scale' => $this->configuration_json['gpa_scale'] ?? null,
            'decimal_places' => $this->configuration_json['decimal_places'] ?? 2,

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'grade_scales' => $this->whenLoaded('gradeScales', GradeScaleResource::collection($this->gradeScales)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('gradeScales'),
                function () {
                    return [
                        'grade_scales_count' => $this->gradeScales->count(),
                        'total_grade_levels' => $this->gradeScales->sum(function ($scale) {
                            return $scale->gradeLevels->count();
                        }),
                    ];
                }
            ),
        ]);
    }

    /**
     * Get human-readable system type label
     */
    private function getSystemTypeLabel(): string
    {
        $labels = [
            'traditional_letter' => 'Letter Grades (A-F)',
            'percentage' => 'Percentage (0-100%)',
            'points' => 'Points System',
            'standards_based' => 'Standards-Based',
            'narrative' => 'Narrative Assessment'
        ];

        return $labels[$this->system_type] ?? ucfirst(str_replace('_', ' ', $this->system_type));
    }
}
EOF

# Grade Scale Resource
cat > app/Http/Resources/Academic/GradeScaleResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradeScaleResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'grading_system_id' => $this->grading_system_id,
            'name' => $this->name,
            'scale_type' => $this->scale_type,
            'is_default' => $this->is_default,

            // Helper fields
            'display_name' => $this->name . ($this->is_default ? ' (Default)' : ''),
            'type_label' => ucfirst(str_replace('_', ' ', $this->scale_type)),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'grading_system' => $this->whenLoaded('gradingSystem', new GradingSystemResource($this->gradingSystem)),
            'grade_levels' => $this->whenLoaded('gradeLevels', GradeLevelResource::collection($this->gradeLevels)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('gradeLevels'),
                function () {
                    $gradeLevels = $this->gradeLevels;
                    return [
                        'grade_levels_count' => $gradeLevels->count(),
                        'passing_levels_count' => $gradeLevels->where('is_passing', true)->count(),
                        'failing_levels_count' => $gradeLevels->where('is_passing', false)->count(),
                        'gpa_range' => [
                            'min' => $gradeLevels->min('gpa_points'),
                            'max' => $gradeLevels->max('gpa_points'),
                        ],
                    ];
                }
            ),
        ]);
    }
}
EOF

# Grade Level Resource
cat > app/Http/Resources/Academic/GradeLevelResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradeLevelResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade_scale_id' => $this->grade_scale_id,
            'grade_value' => $this->grade_value,
            'display_value' => $this->display_value,
            'numeric_value' => $this->formatDecimal($this->numeric_value),
            'gpa_points' => $this->formatDecimal($this->gpa_points),
            'percentage_min' => $this->formatDecimal($this->percentage_min),
            'percentage_max' => $this->formatDecimal($this->percentage_max),
            'description' => $this->description,
            'color_code' => $this->color_code,
            'is_passing' => $this->is_passing,
            'sort_order' => $this->sort_order,

            // Helper fields
            'status_label' => $this->is_passing ? 'Passing' : 'Failing',
            'percentage_range' => $this->percentage_min !== null && $this->percentage_max !== null
                ? $this->percentage_min . '% - ' . $this->percentage_max . '%'
                : null,

            // Relationships
            'grade_scale' => $this->whenLoaded('gradeScale', new GradeScaleResource($this->gradeScale)),

            'meta' => [
                'created_at' => $this->formatDateTime($this->created_at),
                'updated_at' => $this->formatDateTime($this->updated_at),
            ]
        ];
    }
}
EOF

# Grade Entry Resource
cat > app/Http/Resources/Academic/GradeEntryResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradeEntryResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'student_id' => $this->student_id,
            'class_id' => $this->class_id,
            'academic_term_id' => $this->academic_term_id,
            'assessment_name' => $this->assessment_name,
            'assessment_type' => $this->assessment_type,
            'assessment_date' => $this->formatDate($this->assessment_date),
            'raw_score' => $this->formatDecimal($this->raw_score),
            'percentage_score' => $this->formatDecimal($this->percentage_score),
            'letter_grade' => $this->letter_grade,
            'points_earned' => $this->formatDecimal($this->points_earned),
            'points_possible' => $this->formatDecimal($this->points_possible),
            'grade_category' => $this->grade_category,
            'weight' => $this->formatDecimal($this->weight),
            'entered_by' => $this->entered_by,
            'entered_at' => $this->formatDateTime($this->entered_at),
            'modified_by' => $this->modified_by,
            'modified_at' => $this->formatDateTime($this->modified_at),
            'teacher_comments' => $this->teacher_comments,
            'private_notes' => $this->when(
                auth()->user() && (auth()->user()->user_type === 'teacher' || auth()->user()->user_type === 'admin'),
                $this->private_notes
            ),

            // Calculated fields
            'calculated_percentage' => $this->formatDecimal($this->calculatePercentage()),
            'weighted_score' => $this->formatDecimal($this->getWeightedScore()),
            'is_passing' => $this->isPassing(),
            'has_comments' => $this->hasComments(),
            'was_modified' => $this->wasModified(),

            // Display helpers
            'assessment_type_label' => ucfirst(str_replace('_', ' ', $this->assessment_type)),
            'grade_display' => $this->getGradeDisplay(),
            'score_display' => $this->getScoreDisplay(),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'student' => $this->whenLoaded('student', new StudentResource($this->student)),
            'class' => $this->whenLoaded('class', new AcademicClassResource($this->class)),
            'academic_term' => $this->whenLoaded('academicTerm', new AcademicTermResource($this->academicTerm)),
            'entered_by_user' => $this->whenLoaded('enteredBy', new UserResource($this->enteredBy)),
            'modified_by_user' => $this->whenLoaded('modifiedBy', new UserResource($this->modifiedBy)),
        ]);
    }

    /**
     * Get formatted grade display
     */
    private function getGradeDisplay(): string
    {
        if ($this->letter_grade) {
            return $this->letter_grade . ($this->percentage_score ? ' (' . $this->percentage_score . '%)' : '');
        }

        if ($this->percentage_score) {
            return $this->percentage_score . '%';
        }

        if ($this->points_earned !== null && $this->points_possible !== null) {
            return $this->points_earned . '/' . $this->points_possible;
        }

        return $this->raw_score ? (string) $this->raw_score : 'N/A';
    }

    /**
     * Get formatted score display
     */
    private function getScoreDisplay(): string
    {
        if ($this->points_earned !== null && $this->points_possible !== null) {
            $percentage = $this->points_possible > 0
                ? round(($this->points_earned / $this->points_possible) * 100, 1)
                : 0;
            return $this->points_earned . '/' . $this->points_possible . ' (' . $percentage . '%)';
        }

        if ($this->percentage_score !== null) {
            return $this->percentage_score . '%';
        }

        return $this->raw_score ? (string) $this->raw_score : 'N/A';
    }
}
EOF

# Additional helper resources
cat > app/Http/Resources/Academic/StudentResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class StudentResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'display_name' => $this->preferred_name ?: $this->first_name,
            'current_grade_level' => $this->current_grade_level,
            'enrollment_status' => $this->enrollment_status,
            'current_gpa' => $this->formatDecimal($this->current_gpa),
            'attendance_rate' => $this->formatDecimal($this->attendance_rate),
            'behavioral_points' => $this->behavioral_points,

            // Only include sensitive information for authorized users
            'email' => $this->when(auth()->user()->user_type !== 'student', $this->email),
            'phone' => $this->when(auth()->user()->user_type !== 'student', $this->phone),
            'date_of_birth' => $this->when(auth()->user()->user_type !== 'student', $this->formatDate($this->date_of_birth)),
        ]);
    }
}
EOF

cat > app/Http/Resources/Academic/TeacherResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class TeacherResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'email' => $this->email,
            'user_type' => $this->user_type,
            'employee_id' => $this->employee_id,
            'status' => $this->status,
        ];
    }
}
EOF

cat > app/Http/Resources/Academic/SchoolResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class SchoolResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_code' => $this->school_code,
            'official_name' => $this->official_name,
            'display_name' => $this->display_name,
            'short_name' => $this->short_name,
            'school_type' => $this->school_type,
            'educational_levels' => $this->educational_levels,
            'grade_range_min' => $this->grade_range_min,
            'grade_range_max' => $this->grade_range_max,
            'grading_system' => $this->grading_system,
            'academic_calendar_type' => $this->academic_calendar_type,
        ];
    }
}
EOF

cat > app/Http/Resources/Academic/UserResource.php << 'EOF'
<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class UserResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'email' => $this->email,
            'user_type' => $this->user_type,
            'status' => $this->status,
        ];
    }
}
EOF

echo "✅ Academic Management API Resources created successfully!"
echo "📁 Resources created in: app/Http/Resources/Academic/"
echo "🔧 Next: Create Routes and Policies"
