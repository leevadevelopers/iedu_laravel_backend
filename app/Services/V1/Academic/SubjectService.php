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
        $schoolId = $this->getCurrentSchoolId();
        $tenantId = $user->tenant_id;

        $query = Subject::tenantScope($tenantId)
            ->where('school_id', $schoolId);

        // Filter by status - if no status filter, exclude archived by default
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', '!=', 'archived'); // Exclude archived subjects by default
        }

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

        // Status filter is handled above in the main logic

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
        $data['school_id'] = $this->getCurrentSchoolId();

        // Generate code automatically if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generateSubjectCode($data['subject_area'], $user->tenant_id, $data['school_id']);
        } else {
            // Validate subject code uniqueness if manually provided
            $this->validateSubjectCode($data['code'], $data['school_id']);
        }

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

        // Remove code from data - it's auto-generated and cannot be updated
        unset($data['code']);

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
        $allowedGrades = $this->getAllowedGradeLevels();

        foreach ($gradeLevels as $grade) {
            if (!in_array($grade, $allowedGrades, true)) {
                throw new \InvalidArgumentException(\"Invalid grade level: {$grade}. Configure levels in School settings first.\");
            }
        }
    }

    /**
     * Resolve grade levels allowed for the current school.
     */
    private function getAllowedGradeLevels(): array
    {
        $school = $this->getCurrentSchool();
        $configured = $school?->getConfiguredGradeLevels() ?? [];

        if (!empty($configured)) {
            return $configured;
        }

        return $this->getDefaultGradeLevels();
    }

    /**
     * Default grade levels used when school has not configured any.
     */
    private function getDefaultGradeLevels(): array
    {
        return [
            'Pre-K', 'K',
            '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12',
            'T1', 'T2', 'T3',
        ];
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
     * Generate subject code automatically
     * Format: {AreaAbbreviation}-code-T{TenantId}
     * Example: Mat-code-T1, Sci-code-T2
     */
    private function generateSubjectCode(string $subjectArea, int $tenantId, int $schoolId): string
    {
        // Map subject areas to abbreviations
        $areaAbbreviations = [
            'mathematics' => 'Mat',
            'science' => 'Sci',
            'language_arts' => 'Lang',
            'social_studies' => 'Soc',
            'foreign_language' => 'For',
            'arts' => 'Art',
            'physical_education' => 'PE',
            'technology' => 'Tech',
            'vocational' => 'Voc',
            'other' => 'Oth'
        ];

        $abbreviation = $areaAbbreviations[$subjectArea] ?? 'Sub';
        $baseCode = strtoupper($abbreviation) . '-code-T' . $tenantId;

        // Check if code already exists and append sequence number if needed
        $code = $baseCode;
        $counter = 1;

        while (Subject::where('tenant_id', $tenantId)
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->exists()) {
            $code = $baseCode . '-' . $counter;
            $counter++;
        }

        return $code;
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

        // Try to get current school ID, but don't fail if user has no schools
        try {
            $currentSchoolId = $this->getCurrentSchoolId();
            if ($model->school_id !== $currentSchoolId) {
                throw new \Exception('Access denied: Resource does not belong to current school');
            }
        } catch (\Exception $e) {
            // If user has no schools, just validate tenant_id (already done above)
            // This allows operations when user has no school associations
        }
    }
}
