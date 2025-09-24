<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\Subject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class SubjectService extends BaseAcademicService
{
    public function __construct()
    {
        // No longer using repositories
    }

    /**
     * Get paginated subjects with filters
     */
    public function getSubjects(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = Subject::where('tenant_id', $user->tenant_id)
            ->where('school_id', $filters['school_id'] ?? $this->getCurrentSchoolId());

        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['subject_area'])) {
            $query->where('subject_area', $filters['subject_area']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_core_subject'])) {
            $query->where('is_core_subject', $filters['is_core_subject']);
        }

        if (isset($filters['is_elective'])) {
            $query->where('is_elective', $filters['is_elective']);
        }

        if (isset($filters['grade_level'])) {
            $query->whereJsonContains('grade_levels', $filters['grade_level']);
        }

        return $query->with(['school', 'classes'])
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create new subject
     */
    public function createSubject(array $data): Subject
    {
        $user = Auth::user();

        // Add tenant_id from authenticated user
        $data['tenant_id'] = $user->tenant_id;

        // Validate subject code uniqueness
        $this->validateSubjectCode($data['code'], $data['school_id']);

        // Validate grade levels
        $this->validateGradeLevels($data['grade_levels'] ?? []);

        // Set default credit hours based on subject area
        if (!isset($data['credit_hours'])) {
            $data['credit_hours'] = $this->getDefaultCreditHours($data['subject_area']);
        }

        return Subject::create($data);
    }

    /**
     * Update subject
     */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        $this->validateTenantAndSchoolOwnership($subject);

        // Validate subject code uniqueness if changed
        if (isset($data['code']) && $data['code'] !== $subject->code) {
            $this->validateSubjectCode($data['code'], $data['school_id'] ?? $subject->school_id);
        }

        // Validate grade levels if changed
        if (isset($data['grade_levels'])) {
            $this->validateGradeLevels($data['grade_levels']);
        }

        $subject->update($data);
        return $subject->fresh();
    }

    /**
     * Archive subject (soft delete)
     */
    public function deleteSubject(Subject $subject): bool
    {
        $this->validateTenantAndSchoolOwnership($subject);

        // Check for active classes
        if ($subject->classes()->where('status', 'active')->exists()) {
            throw new \Exception('Cannot archive subject with active classes');
        }

        $subject->update(['status' => 'archived']);
        return true;
    }

    /**
     * Get subjects by grade level
     */
    public function getSubjectsByGradeLevel(string $gradeLevel, int $schoolId = null): Collection
    {
        $user = Auth::user();

        return Subject::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->whereJsonContains('grade_levels', $gradeLevel)
            ->active()
            ->with(['school'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get core subjects
     */
    public function getCoreSubjects(int $schoolId = null): Collection
    {
        $user = Auth::user();

        return Subject::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->core()
            ->active()
            ->with(['school'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get elective subjects
     */
    public function getElectiveSubjects(int $schoolId = null): Collection
    {
        $user = Auth::user();

        return Subject::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->elective()
            ->active()
            ->with(['school'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get subjects by area
     */
    public function getSubjectsByArea(string $area, int $schoolId = null): Collection
    {
        $user = Auth::user();

        return Subject::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->byArea($area)
            ->active()
            ->with(['school'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get subject statistics
     */
    public function getSubjectStatistics(int $schoolId = null): array
    {
        $user = Auth::user();
        $schoolId = $schoolId ?? $this->getCurrentSchoolId();

        $query = Subject::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId);

        return [
            'total' => $query->count(),
            'core' => $query->clone()->core()->count(),
            'electives' => $query->clone()->elective()->count(),
            'by_area' => $query->clone()
                ->selectRaw('subject_area, COUNT(*) as count')
                ->groupBy('subject_area')
                ->pluck('count', 'subject_area'),
            'by_grade' => $query->clone()
                ->get()
                ->flatMap(function ($subject) {
                    return collect($subject->grade_levels ?? [])->map(function ($grade) use ($subject) {
                        return ['grade' => $grade, 'subject_id' => $subject->id];
                    });
                })
                ->groupBy('grade')
                ->map->count()
        ];
    }

    /**
     * Validate subject code uniqueness
     */
    private function validateSubjectCode(string $code, int $schoolId): void
    {
        $user = Auth::user();

        if (Subject::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->exists()) {
            throw new \Exception('Subject code already exists');
        }
    }

    /**
     * Validate grade levels
     */
    private function validateGradeLevels(array $gradeLevels): void
    {
        $validGrades = ['K', 'Pre-K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

        foreach ($gradeLevels as $grade) {
            if (!in_array($grade, $validGrades)) {
                throw new \InvalidArgumentException("Invalid grade level: {$grade}");
            }
        }
    }

    /**
     * Get default credit hours based on subject area
     */
    private function getDefaultCreditHours(string $subjectArea): float
    {
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

        return $defaultCredits[$subjectArea] ?? 1.0;
    }

    /**
     * Validate tenant and school ownership
     */
    private function validateTenantAndSchoolOwnership($model): void
    {
        $user = Auth::user();

        if ($model->tenant_id !== $user->tenant_id) {
            throw new \Exception('Access denied: Resource does not belong to current tenant');
        }

        if ($model->school_id !== $this->getCurrentSchoolId()) {
            throw new \Exception('Access denied: Resource does not belong to current school');
        }
    }
}
