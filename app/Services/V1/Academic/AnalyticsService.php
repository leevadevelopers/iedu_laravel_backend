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

        // Get grade distribution
        $gradeDistribution = $this->getGradeDistributionData($filters);

        // Get recent activity
        $recentActivity = $this->getRecentActivity($filters);

        // Get performance metrics
        $performanceMetrics = $this->getPerformanceMetrics($filters);

        return [
            'overview' => [
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'total_classes' => $totalClasses,
                'total_subjects' => $totalSubjects,
            ],
            'grade_distribution' => $gradeDistribution,
            'recent_activity' => $recentActivity,
            'performance_metrics' => $performanceMetrics,
        ];
    }

    /**
     * Get grade distribution analytics
     */
    public function getGradeDistribution(array $filters = []): array
    {
        $query = GradeEntry::with(['gradeLevel', 'student', 'subject', 'academicClass'])
            ->whereHas('academicClass', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            });

        // Apply filters
        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('academic_class_id', $filters['class_id']);
        }

        if (isset($filters['term'])) {
            $query->where('term', $filters['term']);
        }

        $gradeEntries = $query->get();

        // Group by grade level
        $distribution = $gradeEntries->groupBy('grade_level_id')->map(function ($entries, $gradeLevelId) {
            $gradeLevel = $entries->first()->gradeLevel;
            return [
                'grade_level_id' => $gradeLevelId,
                'grade_value' => $gradeLevel->grade_value,
                'display_value' => $gradeLevel->display_value,
                'count' => $entries->count(),
                'percentage' => 0, // Will be calculated below
                'color_code' => $gradeLevel->color_code,
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
        $query = GradeEntry::with(['subject', 'gradeLevel'])
            ->whereHas('academicClass', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            });

        // Apply filters
        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['grade_level'])) {
            $query->whereHas('academicClass', function ($q) use ($filters) {
                $q->where('grade_level', $filters['grade_level']);
            });
        }

        if (isset($filters['term'])) {
            $query->where('term', $filters['term']);
        }

        $gradeEntries = $query->get();

        // Group by subject
        $subjectPerformance = $gradeEntries->groupBy('subject_id')->map(function ($entries, $subjectId) {
            $subject = $entries->first()->subject;
            $totalEntries = $entries->count();
            $passingEntries = $entries->where('gradeLevel.is_passing', true)->count();
            $averageGPA = $entries->avg('gpa_points') ?? 0;

            return [
                'subject_id' => $subjectId,
                'subject_name' => $subject->name,
                'subject_code' => $subject->code,
                'total_entries' => $totalEntries,
                'passing_count' => $passingEntries,
                'failing_count' => $totalEntries - $passingEntries,
                'pass_rate' => $totalEntries > 0 ? round(($passingEntries / $totalEntries) * 100, 2) : 0,
                'average_gpa' => round($averageGPA, 2),
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
                $gradeEntriesQuery->where('academic_year_id', $filters['academic_year_id']);
            }

            $gradeEntries = $gradeEntriesQuery->get();
            $totalGrades = $gradeEntries->count();
            $averageGPA = $gradeEntries->avg('gpa_points') ?? 0;

            return [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name,
                'department' => $teacher->department,
                'position' => $teacher->position,
                'total_classes' => $classes->count(),
                'total_students' => $totalStudents,
                'total_grades_entered' => $totalGrades,
                'average_gpa' => round($averageGPA, 2),
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
        $class = AcademicClass::with(['students', 'subject', 'primaryTeacher'])
            ->where('id', $classId)
            ->where('school_id', $this->getCurrentSchoolId())
            ->firstOrFail();

        // Get grade entries for this class
        $gradeEntriesQuery = GradeEntry::with(['gradeLevel', 'student'])
            ->where('academic_class_id', $classId);

        if (isset($filters['academic_year_id'])) {
            $gradeEntriesQuery->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['term'])) {
            $gradeEntriesQuery->where('term', $filters['term']);
        }

        $gradeEntries = $gradeEntriesQuery->get();

        $totalStudents = $class->students->count();
        $totalGrades = $gradeEntries->count();
        $passingGrades = $gradeEntries->where('gradeLevel.is_passing', true)->count();
        $averageGPA = $gradeEntries->avg('gpa_points') ?? 0;

        // Grade distribution
        $gradeDistribution = $gradeEntries->groupBy('grade_level_id')->map(function ($entries, $gradeLevelId) {
            $gradeLevel = $entries->first()->gradeLevel;
            return [
                'grade_value' => $gradeLevel->grade_value,
                'display_value' => $gradeLevel->display_value,
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
                'subject' => $class->subject->name,
                'primary_teacher' => $class->primaryTeacher->full_name,
                'grade_level' => $class->grade_level,
                'current_enrollment' => $class->current_enrollment,
            ],
            'statistics' => [
                'total_students' => $totalStudents,
                'total_grades' => $totalGrades,
                'passing_grades' => $passingGrades,
                'failing_grades' => $totalGrades - $passingGrades,
                'pass_rate' => $totalGrades > 0 ? round(($passingGrades / $totalGrades) * 100, 2) : 0,
                'average_gpa' => round($averageGPA, 2),
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

        $query = GradeEntry::with(['gradeLevel', 'subject', 'academicClass'])
            ->where('student_id', $studentId)
            ->whereHas('academicClass', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            });

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        $gradeEntries = $query->orderBy('created_at')->get();

        // Group by term or month
        $trends = $gradeEntries->groupBy(function ($entry) {
            return $entry->term ?? $entry->created_at->format('Y-m');
        })->map(function ($entries, $period) {
            return [
                'period' => $period,
                'average_gpa' => round($entries->avg('gpa_points'), 2),
                'total_grades' => $entries->count(),
                'passing_grades' => $entries->where('gradeLevel.is_passing', true)->count(),
            ];
        })->values();

        return [
            'student_id' => $studentId,
            'trends' => $trends,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get attendance analytics
     */
    public function getAttendanceAnalytics(array $filters = []): array
    {
        // This would integrate with attendance system
        // For now, return placeholder data
        return [
            'message' => 'Attendance analytics integration pending',
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get comparative analytics
     */
    public function getComparativeAnalytics(array $filters = []): array
    {
        $comparisonType = $filters['comparison_type'];
        $entityIds = $filters['entity_ids'];

        $comparison = [];

        switch ($comparisonType) {
            case 'classes':
                $comparison = $this->compareClasses($entityIds, $filters);
                break;
            case 'subjects':
                $comparison = $this->compareSubjects($entityIds, $filters);
                break;
            case 'teachers':
                $comparison = $this->compareTeachers($entityIds, $filters);
                break;
            case 'academic_years':
                $comparison = $this->compareAcademicYears($entityIds, $filters);
                break;
        }

        return [
            'comparison_type' => $comparisonType,
            'entities' => $comparison,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Export analytics data
     */
    public function exportAnalytics(array $filters = []): array
    {
        $reportType = $filters['report_type'];
        $format = $filters['format'];

        // This would generate actual export files
        // For now, return placeholder data
        return [
            'report_type' => $reportType,
            'format' => $format,
            'download_url' => null, // Would be actual download URL
            'message' => 'Export functionality pending implementation',
        ];
    }

    /**
     * Helper methods for analytics
     */
    protected function getGradeDistributionData(array $filters = []): array
    {
        // Implementation for grade distribution data
        return [];
    }

    protected function getRecentActivity(array $filters = []): array
    {
        // Implementation for recent activity
        return [];
    }

    protected function getPerformanceMetrics(array $filters = []): array
    {
        // Implementation for performance metrics
        return [];
    }

    protected function compareClasses(array $classIds, array $filters): array
    {
        // Implementation for class comparison
        return [];
    }

    protected function compareSubjects(array $subjectIds, array $filters): array
    {
        // Implementation for subject comparison
        return [];
    }

    protected function compareTeachers(array $teacherIds, array $filters): array
    {
        // Implementation for teacher comparison
        return [];
    }

    protected function compareAcademicYears(array $yearIds, array $filters): array
    {
        // Implementation for academic year comparison
        return [];
    }
}
