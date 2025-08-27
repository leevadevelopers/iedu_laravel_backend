<?php

namespace App\Repositories\V1\SIS\Eloquent;

use App\Models\V1\SIS\Student\Student;
use App\Repositories\V1\SIS\Contracts\StudentRepositoryInterface;
use App\Services\SchoolContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Eloquent Student Repository
 *
 * Implementation of the StudentRepositoryInterface using Eloquent ORM
 * with automatic school context scoping and educational business logic.
 */
class EloquentStudentRepository implements StudentRepositoryInterface
{
    protected Student $model;
    protected SchoolContextService $schoolContext;

    public function __construct(Student $model, SchoolContextService $schoolContext)
    {
        $this->model = $model;
        $this->schoolContext = $schoolContext;
    }

    /**
     * Get a new query builder with school scoping applied.
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery()
            ->where('school_id', $this->schoolContext->getCurrentSchoolId());
    }

    /**
     * Find student by ID with school scoping.
     */
    public function find(int $id): ?Student
    {
        return $this->newQuery()->find($id);
    }

    /**
     * Find student by student number within school.
     */
    public function findByStudentNumber(string $studentNumber): ?Student
    {
        return $this->newQuery()
            ->where('student_number', $studentNumber)
            ->first();
    }

    /**
     * Create a new student record.
     */
    public function create(array $data): Student
    {
        $data['school_id'] = $this->schoolContext->getCurrentSchoolId();

        // Generate student number if not provided
        if (empty($data['student_number'])) {
            $data['student_number'] = $this->generateStudentNumber();
        }

        return $this->model->create($data);
    }

    /**
     * Update an existing student record.
     */
    public function update(int $id, array $data): Student
    {
        $student = $this->newQuery()->findOrFail($id);

        $student->update($data);

        return $student->fresh();
    }

    /**
     * Delete a student record (soft delete).
     */
    public function delete(int $id): bool
    {
        $student = $this->newQuery()->findOrFail($id);

        return $student->delete();
    }

    /**
     * Get paginated list of students with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->newQuery()->with([
            'currentAcademicYear',
            'familyRelationships.guardian'
        ]);

        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('last_name')
                    ->orderBy('first_name')
                    ->paginate($perPage);
    }

    /**
     * Search students by name, student number, or other criteria.
     */
    public function search(string $searchQuery, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->newQuery()->with([
            'currentAcademicYear',
            'familyRelationships.guardian'
        ]);

        // Apply search criteria
        $query->where(function ($q) use ($searchQuery) {
            $q->where('first_name', 'like', "%{$searchQuery}%")
              ->orWhere('last_name', 'like', "%{$searchQuery}%")
              ->orWhere('student_number', 'like', "%{$searchQuery}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchQuery}%"]);
        });

        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('last_name')
                    ->orderBy('first_name')
                    ->paginate($perPage);
    }

    /**
     * Get students by enrollment status.
     */
    public function getByEnrollmentStatus(string $status): Collection
    {
        return $this->newQuery()
            ->where('enrollment_status', $status)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get students by grade level.
     */
    public function getByGradeLevel(string $gradeLevel): Collection
    {
        return $this->newQuery()
            ->where('current_grade_level', $gradeLevel)
            ->where('enrollment_status', 'enrolled')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get students requiring document verification.
     */
    public function getStudentsRequiringDocuments(): Collection
    {
        return $this->newQuery()
            ->whereHas('documents', function ($query) {
                $query->where('required', true)
                      ->where('verified', false);
            })
            ->where('enrollment_status', 'enrolled')
            ->with(['documents' => function ($query) {
                $query->where('required', true)
                      ->where('verified', false);
            }])
            ->get();
    }

    /**
     * Get students with missing emergency contacts.
     */
    public function getStudentsWithMissingEmergencyContacts(): Collection
    {
        return $this->newQuery()
            ->where('enrollment_status', 'enrolled')
            ->where(function ($query) {
                $query->whereNull('emergency_contacts_json')
                      ->orWhereRaw('JSON_LENGTH(emergency_contacts_json) = 0');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get student enrollment statistics by grade level.
     */
    public function getEnrollmentStatsByGrade(): array
    {
        $stats = $this->newQuery()
            ->selectRaw('current_grade_level, COUNT(*) as count')
            ->where('enrollment_status', 'enrolled')
            ->groupBy('current_grade_level')
            ->orderBy('current_grade_level')
            ->pluck('count', 'current_grade_level')
            ->toArray();

        return $stats;
    }

    /**
     * Get students with special educational needs.
     */
    public function getStudentsWithSpecialNeeds(): Collection
    {
        return $this->newQuery()
            ->whereNotNull('accommodation_needs_json')
            ->whereRaw('JSON_LENGTH(accommodation_needs_json) > 0')
            ->where('enrollment_status', 'enrolled')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get students by academic year.
     */
    public function getByAcademicYear(int $academicYearId): Collection
    {
        return $this->newQuery()
            ->where('current_academic_year_id', $academicYearId)
            ->where('enrollment_status', 'enrolled')
            ->orderBy('current_grade_level')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Bulk update students (for operations like grade promotion).
     */
    public function bulkUpdate(array $studentIds, array $data): bool
    {
        return $this->newQuery()
            ->whereIn('id', $studentIds)
            ->update($data) > 0;
    }

    /**
     * Get student academic summary.
     */
    public function getAcademicSummary(int $studentId): array
    {
        $student = $this->newQuery()
            ->with([
                'gradeEntries.class.subject',
                'attendanceRecords' => function ($query) {
                    $query->where('attendance_date', '>=', Carbon::now()->startOfYear());
                },
                'behavioralIncidents' => function ($query) {
                    $query->where('incident_date', '>=', Carbon::now()->startOfYear());
                }
            ])
            ->findOrFail($studentId);

        $currentYear = Carbon::now()->year;
        $attendanceRecords = $student->attendanceRecords;

        $totalDays = $attendanceRecords->count();
        $presentDays = $attendanceRecords->whereIn('status', ['present', 'late'])->count();
        $attendanceRate = $totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0;

        return [
            'student_id' => $studentId,
            'current_gpa' => $student->current_gpa,
            'attendance_rate' => round($attendanceRate, 2),
            'total_attendance_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $totalDays - $presentDays,
            'behavioral_incidents_count' => $student->behavioralIncidents->count(),
            'grades_count' => $student->gradeEntries->count(),
            'enrollment_status' => $student->enrollment_status,
            'current_grade_level' => $student->current_grade_level,
        ];
    }

    /**
     * Check if student number is available within school.
     */
    public function isStudentNumberAvailable(string $studentNumber): bool
    {
        return !$this->newQuery()
            ->where('student_number', $studentNumber)
            ->exists();
    }

    /**
     * Get students with upcoming birthdays.
     */
    public function getUpcomingBirthdays(int $days = 30): Collection
    {
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays($days);

        return $this->newQuery()
            ->where('enrollment_status', 'enrolled')
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT(?, '%m-%d') AND DATE_FORMAT(?, '%m-%d')",
                      [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderByRaw("DATE_FORMAT(date_of_birth, '%m-%d')")
            ->get();
    }

    /**
     * Apply filters to the query.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['enrollment_status'])) {
            $query->where('enrollment_status', $filters['enrollment_status']);
        }

        if (!empty($filters['grade_level'])) {
            $query->where('current_grade_level', $filters['grade_level']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (!empty($filters['academic_year_id'])) {
            $query->where('current_academic_year_id', $filters['academic_year_id']);
        }

        if (!empty($filters['has_special_needs'])) {
            if ($filters['has_special_needs'] === 'yes') {
                $query->whereNotNull('accommodation_needs_json')
                      ->whereRaw('JSON_LENGTH(accommodation_needs_json) > 0');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('accommodation_needs_json')
                      ->orWhereRaw('JSON_LENGTH(accommodation_needs_json) = 0');
                });
            }
        }

        if (!empty($filters['admission_date_from'])) {
            $query->where('admission_date', '>=', $filters['admission_date_from']);
        }

        if (!empty($filters['admission_date_to'])) {
            $query->where('admission_date', '<=', $filters['admission_date_to']);
        }

        if (!empty($filters['age_from']) || !empty($filters['age_to'])) {
            $ageFrom = $filters['age_from'] ?? 0;
            $ageTo = $filters['age_to'] ?? 100;

            $query->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN ? AND ?',
                            [$ageFrom, $ageTo]);
        }

        return $query;
    }

    /**
     * Generate a unique student number.
     */
    protected function generateStudentNumber(): string
    {
        $schoolId = $this->schoolContext->getCurrentSchoolId();
        $year = Carbon::now()->year;

        // Format: YYYY{school_id_padded}{sequence}
        $schoolIdPadded = str_pad($schoolId, 3, '0', STR_PAD_LEFT);

        $lastStudent = $this->newQuery()
            ->where('student_number', 'like', $year . $schoolIdPadded . '%')
            ->orderByDesc('student_number')
            ->first();

        if ($lastStudent) {
            $lastSequence = (int) substr($lastStudent->student_number, -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        $sequencePadded = str_pad($newSequence, 4, '0', STR_PAD_LEFT);

        return $year . $schoolIdPadded . $sequencePadded;
    }
}
