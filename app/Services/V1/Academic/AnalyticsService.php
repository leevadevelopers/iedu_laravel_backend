<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Support\Facades\DB;

class AnalyticsService extends BaseAcademicService
{
    /**
     * Get academic overview analytics
     */
    public function getAcademicOverview(array $filters = []): array
    {
        $schoolId = $this->getCurrentSchoolId();

        // Get basic counts
        $totalStudents = Student::where('school_id', $schoolId)->count();
        $totalTeachers = Teacher::where('school_id', $schoolId)->count();
        $totalClasses = AcademicClass::where('school_id', $schoolId)->count();
        $totalSubjects = Subject::where('school_id', $schoolId)->count();

        return [
            'overview' => [
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'total_classes' => $totalClasses,
                'total_subjects' => $totalSubjects,
            ],
        ];
    }

    /**
     * Get grade distribution analytics
     */
    public function getGradeDistribution(array $filters = []): array
    {
        $query = GradeEntry::with(['student', 'class.subject', 'academicTerm.academicYear'])
            ->whereHas('class', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            });

        // Apply filters
        if (isset($filters['academic_year_id'])) {
            $query->whereHas('academicTerm', function ($q) use ($filters) {
                $q->where('academic_year_id', $filters['academic_year_id']);
            });
        }

        if (isset($filters['subject_id'])) {
            $query->whereHas('class', function ($q) use ($filters) {
                $q->where('subject_id', $filters['subject_id']);
            });
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['term'])) {
            // Filter by term name or academic_term_id
            if (is_numeric($filters['term'])) {
                $query->where('academic_term_id', $filters['term']);
            } else {
                // If term is a string like 'first_term', we'd need to map it
                // For now, we'll assume it's an academic_term_id
            }
        }

        $gradeEntries = $query->get();

        // Group by letter grade
        $distribution = $gradeEntries->groupBy('letter_grade')->map(function ($entries, $letterGrade) {
            $count = $entries->count();
            return [
                'letter_grade' => $letterGrade,
                'count' => $count,
                'percentage' => 0, // Will be calculated below
            ];
        })->values();

        // Calculate percentages
        $totalEntries = $gradeEntries->count();
        $distribution = $distribution->map(function ($item) use ($totalEntries) {
            $item['percentage'] = $totalEntries > 0 ? round(($item['count'] / $totalEntries) * 100, 2) : 0;
            return $item;
        });

        return [
            'distribution' => $distribution,
            'total_entries' => $totalEntries,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get subject performance analytics
     */
    public function getSubjectPerformance(array $filters = []): array
    {
        $query = GradeEntry::with(['class.subject', 'academicTerm.academicYear'])
            ->whereHas('class', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            });

        // Apply filters
        if (isset($filters['academic_year_id'])) {
            $query->whereHas('academicTerm', function ($q) use ($filters) {
                $q->where('academic_year_id', $filters['academic_year_id']);
            });
        }

        if (isset($filters['grade_level'])) {
            $query->whereHas('class', function ($q) use ($filters) {
                $q->where('grade_level', $filters['grade_level']);
            });
        }

        if (isset($filters['term'])) {
            if (is_numeric($filters['term'])) {
                $query->where('academic_term_id', $filters['term']);
            }
        }

        $gradeEntries = $query->get();

        // Group by subject
        $subjectPerformance = $gradeEntries->groupBy(function ($entry) {
            return $entry->class->subject_id;
        })->map(function ($entries, $subjectId) {
            $subject = $entries->first()->class->subject;
            $totalEntries = $entries->count();

            // Calculate passing based on percentage_score >= 60
            $passingEntries = $entries->filter(function ($entry) {
                return $entry->percentage_score >= 60;
            })->count();

            $averagePercentage = $entries->avg('percentage_score') ?? 0;

            return [
                'subject_id' => $subjectId,
                'subject_name' => $subject->name ?? 'Unknown',
                'subject_code' => $subject->code ?? '',
                'total_entries' => $totalEntries,
                'passing_count' => $passingEntries,
                'failing_count' => $totalEntries - $passingEntries,
                'pass_rate' => $totalEntries > 0 ? round(($passingEntries / $totalEntries) * 100, 2) : 0,
                'average_percentage' => round($averagePercentage, 2),
            ];
        })->values();

        return [
            'subject_performance' => $subjectPerformance,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get teacher statistics
     */
    public function getTeacherStats(array $filters = []): array
    {
        $query = Teacher::with(['classes', 'gradeEntries'])
            ->where('school_id', $this->getCurrentSchoolId());

        // Apply filters
        if (isset($filters['teacher_id'])) {
            $query->where('id', $filters['teacher_id']);
        }

        if (isset($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        $teachers = $query->get();

        $teacherStats = $teachers->map(function ($teacher) use ($filters) {
            $classes = $teacher->classes;
            $totalStudents = $classes->sum('current_enrollment');

            // Get grade entries for this teacher
            $gradeEntriesQuery = GradeEntry::where('entered_by', $teacher->id);

            if (isset($filters['academic_year_id'])) {
                $gradeEntriesQuery->whereHas('academicTerm', function ($q) use ($filters) {
                    $q->where('academic_year_id', $filters['academic_year_id']);
                });
            }

            $gradeEntries = $gradeEntriesQuery->get();
            $totalGrades = $gradeEntries->count();
            $averagePercentage = $gradeEntries->avg('percentage_score') ?? 0;

            return [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name,
                'department' => $teacher->department,
                'position' => $teacher->position,
                'total_classes' => $classes->count(),
                'total_students' => $totalStudents,
                'total_grades_entered' => $totalGrades,
                'average_percentage' => round($averagePercentage, 2),
                'years_of_service' => $teacher->getYearsOfService(),
            ];
        });

        return [
            'teacher_stats' => $teacherStats,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get class statistics
     */
    public function getClassStats(int $classId, array $filters = []): array
    {
        $schoolId = $this->getCurrentSchoolId();
        $tenantId = $this->getCurrentTenantId();

        $class = AcademicClass::with(['students', 'subject', 'primaryTeacher'])
            ->where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$class) {
            throw new \Exception('Class not found or you do not have access to it');
        }

        // Get grade entries for this class
        $gradeEntriesQuery = GradeEntry::with(['student', 'academicTerm'])
            ->where('class_id', $classId);

        if (isset($filters['academic_year_id'])) {
            $gradeEntriesQuery->whereHas('academicTerm', function ($q) use ($filters) {
                $q->where('academic_year_id', $filters['academic_year_id']);
            });
        }

        if (isset($filters['term'])) {
            if (is_numeric($filters['term'])) {
                $gradeEntriesQuery->where('academic_term_id', $filters['term']);
            }
        }

        $gradeEntries = $gradeEntriesQuery->get();

        $totalStudents = $class->students->count();
        $totalGrades = $gradeEntries->count();
        $passingGrades = $gradeEntries->filter(function ($entry) {
            return $entry->percentage_score >= 60;
        })->count();
        $averagePercentage = $gradeEntries->avg('percentage_score') ?? 0;

        // Grade distribution by letter grade
        $gradeDistribution = $gradeEntries->groupBy('letter_grade')->map(function ($entries, $letterGrade) {
            return [
                'letter_grade' => $letterGrade,
                'count' => $entries->count(),
                'percentage' => 0, // Will be calculated
            ];
        })->values();

        // Calculate percentages
        $gradeDistribution = $gradeDistribution->map(function ($item) use ($totalGrades) {
            $item['percentage'] = $totalGrades > 0 ? round(($item['count'] / $totalGrades) * 100, 2) : 0;
            return $item;
        });

        return [
            'class_info' => [
                'id' => $class->id,
                'name' => $class->name,
                'subject' => $class->subject ? $class->subject->name : null,
                'primary_teacher' => $class->primaryTeacher ? $class->primaryTeacher->full_name : null,
                'grade_level' => $class->grade_level,
                'current_enrollment' => $class->current_enrollment,
            ],
            'statistics' => [
                'total_students' => $totalStudents,
                'total_grades' => $totalGrades,
                'passing_grades' => $passingGrades,
                'failing_grades' => $totalGrades - $passingGrades,
                'pass_rate' => $totalGrades > 0 ? round(($passingGrades / $totalGrades) * 100, 2) : 0,
                'average_percentage' => round($averagePercentage, 2),
            ],
            'grade_distribution' => $gradeDistribution,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get student performance trends
     */
    public function getStudentPerformanceTrends(array $filters = []): array
    {
        $studentId = $filters['student_id'];

        $query = GradeEntry::with(['class.subject', 'academicTerm'])
            ->where('student_id', $studentId)
            ->whereHas('class', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            });

        if (isset($filters['academic_year_id'])) {
            $query->whereHas('academicTerm', function ($q) use ($filters) {
                $q->where('academic_year_id', $filters['academic_year_id']);
            });
        }

        if (isset($filters['subject_id'])) {
            $query->whereHas('class', function ($q) use ($filters) {
                $q->where('subject_id', $filters['subject_id']);
            });
        }

        $gradeEntries = $query->orderBy('created_at')->get();

        // Group by term or month
        $trends = $gradeEntries->groupBy(function ($entry) {
            return $entry->academicTerm ? $entry->academicTerm->name : $entry->created_at->format('Y-m');
        })->map(function ($entries, $period) {
            return [
                'period' => $period,
                'average_percentage' => round($entries->avg('percentage_score'), 2),
                'total_grades' => $entries->count(),
                'passing_grades' => $entries->filter(function ($entry) {
                    return $entry->percentage_score >= 60;
                })->count(),
            ];
        })->values();

        return [
            'student_id' => $studentId,
            'trends' => $trends,
            'filters_applied' => $filters,
        ];
    }

}
