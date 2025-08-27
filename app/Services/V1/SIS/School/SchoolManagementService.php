<?php

namespace App\Services\V1\SIS\School;

use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchoolManagementService
{
    /**
     * Create a new school
     */
    public function createSchool(array $schoolData): School
    {
        return DB::transaction(function () use ($schoolData) {
            $school = School::create([
                'tenant_id' => auth()->user()?->current_tenant_id ?? 1,
                'school_code' => $this->generateSchoolCode(),
                'official_name' => $schoolData['official_name'],
                'display_name' => $schoolData['display_name'] ?? $schoolData['official_name'],
                'short_name' => $schoolData['short_name'] ?? $this->generateShortName($schoolData['official_name']),
                'school_type' => $schoolData['school_type'] ?? 'public',
                'educational_levels' => $schoolData['educational_levels'] ?? ['elementary'],
                'grade_range_min' => $schoolData['grade_range_min'] ?? 'K',
                'grade_range_max' => $schoolData['grade_range_max'] ?? '5',
                'email' => $schoolData['email'],
                'phone' => $schoolData['phone'] ?? null,
                'website' => $schoolData['website'] ?? null,
                'address_json' => $schoolData['address_json'] ?? null,
                'country_code' => $schoolData['country_code'] ?? 'US',
                'state_province' => $schoolData['state_province'] ?? null,
                'city' => $schoolData['city'] ?? 'Unknown',
                'timezone' => $schoolData['timezone'] ?? 'UTC',
                'ministry_education_code' => $schoolData['ministry_education_code'] ?? null,
                'accreditation_status' => $schoolData['accreditation_status'] ?? 'candidate',
                'academic_calendar_type' => $schoolData['academic_calendar_type'] ?? 'semester',
                'academic_year_start_month' => $schoolData['academic_year_start_month'] ?? 8,
                'grading_system' => $schoolData['grading_system'] ?? 'traditional_letter',
                'attendance_tracking_level' => $schoolData['attendance_tracking_level'] ?? 'daily',
                'educational_philosophy' => $schoolData['educational_philosophy'] ?? null,
                'language_instruction' => $schoolData['language_instruction'] ?? ['en'],
                'religious_affiliation' => $schoolData['religious_affiliation'] ?? null,
                'student_capacity' => $schoolData['student_capacity'] ?? null,
                'established_date' => $schoolData['established_date'] ?? null,
                'subscription_plan' => $schoolData['subscription_plan'] ?? 'basic',
                'feature_flags' => $schoolData['feature_flags'] ?? [],
                'integration_settings' => $schoolData['integration_settings'] ?? [],
                'branding_configuration' => $schoolData['branding_configuration'] ?? [],
                'status' => 'setup'
            ]);

            // Create default academic year if specified
            if (!empty($schoolData['create_default_academic_year'])) {
                $this->createDefaultAcademicYear($school);
            }

            Log::info('School created', [
                'school_id' => $school->id,
                'school_code' => $school->school_code,
                'name' => $school->official_name,
            ]);

            return $school;
        });
    }

    /**
     * Update an existing school
     */
    public function updateSchool(int $schoolId, array $schoolData): School
    {
        $school = School::findOrFail($schoolId);

        return DB::transaction(function () use ($school, $schoolData) {
            $school->update($schoolData);

            Log::info('School updated', [
                'school_id' => $school->id,
                'school_code' => $school->school_code,
                'updated_fields' => array_keys($schoolData),
            ]);

            return $school;
        });
    }

    /**
     * Get school by ID with full information
     */
    public function getSchool(int $schoolId): ?School
    {
        $school = School::find($schoolId);

        if ($school) {
            $school->load([
                'academicYears' => function ($query) {
                    $query->orderBy('start_date', 'desc');
                },
                'academicYears.academicTerms' => function ($query) {
                    $query->orderBy('term_number');
                }
            ]);
        }

        return $school;
    }

    /**
     * Get school by school code
     */
    public function getSchoolByCode(string $schoolCode): ?School
    {
        return School::where('school_code', $schoolCode)->first();
    }

    /**
     * Get paginated list of schools with filtering
     */
    public function getSchools(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = School::query();

        // Apply filters
        if (!empty($filters['school_type'])) {
            $query->where('school_type', $filters['school_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['country_code'])) {
            $query->where('country_code', $filters['country_code']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('official_name', 'like', "%{$filters['search']}%")
                  ->orWhere('display_name', 'like', "%{$filters['search']}%")
                  ->orWhere('school_code', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('official_name')->paginate($perPage);
    }

    /**
     * Create a new academic year for a school
     */
    public function createAcademicYear(int $schoolId, array $academicYearData): AcademicYear
    {
        $school = School::findOrFail($schoolId);

        return DB::transaction(function () use ($school, $academicYearData) {
            // If this is set as current, deactivate other current academic years
            if (!empty($academicYearData['is_current']) && $academicYearData['is_current']) {
                AcademicYear::where('school_id', $schoolId)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            $academicYear = AcademicYear::create([
                'school_id' => $schoolId,
                'name' => $academicYearData['name'],
                'code' => $academicYearData['code'] ?? $this->generateAcademicYearCode($academicYearData['name']),
                'start_date' => $academicYearData['start_date'],
                'end_date' => $academicYearData['end_date'],
                'term_structure' => $academicYearData['term_structure'] ?? 'semesters',
                'total_terms' => $academicYearData['total_terms'] ?? 2,
                'total_instructional_days' => $academicYearData['total_instructional_days'] ?? null,
                'status' => $academicYearData['status'] ?? 'planning',
                'is_current' => $academicYearData['is_current'] ?? false
            ]);

            // Create default terms if specified
            if (!empty($academicYearData['create_default_terms'])) {
                $this->createDefaultTerms($academicYear);
            }

            Log::info('Academic year created', [
                'school_id' => $schoolId,
                'academic_year_id' => $academicYear->id,
                'name' => $academicYear->name,
            ]);

            return $academicYear;
        });
    }

    /**
     * Create default academic year for a new school
     */
    private function createDefaultAcademicYear(School $school): AcademicYear
    {
        $currentYear = now()->year;
        $nextYear = $currentYear + 1;

        $academicYear = AcademicYear::create([
            'school_id' => $school->id,
            'name' => "{$currentYear}-{$nextYear}",
            'code' => "AY{$currentYear}",
            'start_date' => "{$currentYear}-08-01",
            'end_date' => "{$nextYear}-06-30",
            'term_structure' => 'semesters',
            'total_terms' => 2,
            'status' => 'planning',
            'is_current' => true
        ]);

        // Create default terms
        $this->createDefaultTerms($academicYear);

        return $academicYear;
    }

    /**
     * Create default terms for an academic year
     */
    private function createDefaultTerms(AcademicYear $academicYear): void
    {
        $terms = [
            [
                'name' => 'Fall Semester',
                'term_number' => 1,
                'start_date' => $academicYear->start_date,
                'end_date' => date('Y-12-31', strtotime($academicYear->start_date)),
                'instructional_days' => 90
            ],
            [
                'name' => 'Spring Semester',
                'term_number' => 2,
                'start_date' => date('Y-01-01', strtotime($academicYear->end_date)),
                'end_date' => $academicYear->end_date,
                'instructional_days' => 90
            ]
        ];

        foreach ($terms as $termData) {
            AcademicTerm::create(array_merge($termData, [
                'academic_year_id' => $academicYear->id,
                'school_id' => $academicYear->school_id,
                'status' => 'planned'
            ]));
        }
    }

    /**
     * Get school statistics
     */
    public function getSchoolStatistics(int $schoolId): array
    {
        $school = School::findOrFail($schoolId);

        $academicYears = $school->academicYears;
        $currentAcademicYear = $academicYears->where('is_current', true)->first();

        return [
            'school_info' => [
                'id' => $school->id,
                'name' => $school->official_name,
                'school_code' => $school->school_code,
                'status' => $school->status,
                'school_type' => $school->school_type,
                'established_date' => $school->established_date?->format('Y-m-d'),
            ],
            'enrollment' => [
                'current_enrollment' => $school->current_enrollment,
                'student_capacity' => $school->student_capacity,
                'enrollment_percentage' => $school->student_capacity > 0
                    ? round(($school->current_enrollment / $school->student_capacity) * 100, 2)
                    : 0,
            ],
            'academic_structure' => [
                'total_academic_years' => $academicYears->count(),
                'current_academic_year' => $currentAcademicYear ? [
                    'name' => $currentAcademicYear->name,
                    'start_date' => $currentAcademicYear->start_date->format('Y-m-d'),
                    'end_date' => $currentAcademicYear->end_date->format('Y-m-d'),
                    'status' => $currentAcademicYear->status
                ] : null,
                'grade_range' => "{$school->grade_range_min} - {$school->grade_range_max}",
                'educational_levels' => $school->educational_levels,
            ],
            'operational' => [
                'staff_count' => $school->staff_count,
                'subscription_plan' => $school->subscription_plan,
                'accreditation_status' => $school->accreditation_status,
                'timezone' => $school->timezone,
            ]
        ];
    }

    /**
     * Update school enrollment count
     */
    public function updateEnrollmentCount(int $schoolId, int $change = 0): bool
    {
        $school = School::findOrFail($schoolId);

        $newCount = max(0, $school->current_enrollment + $change);
        $school->update(['current_enrollment' => $newCount]);

        return true;
    }

    /**
     * Activate a school (complete setup)
     */
    public function activateSchool(int $schoolId): bool
    {
        $school = School::findOrFail($schoolId);

        // Validate that school has required setup completed
        if (empty($school->academicYears)) {
            throw new \Exception('School must have at least one academic year before activation');
        }

        $school->update([
            'status' => 'active',
            'onboarding_completed_at' => now()
        ]);

        Log::info('School activated', [
            'school_id' => $schoolId,
            'school_code' => $school->school_code,
        ]);

        return true;
    }

    /**
     * Generate unique school code
     */
    private function generateSchoolCode(): string
    {
        do {
            $code = 'SCH-' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (School::where('school_code', $code)->exists());

        return $code;
    }

    /**
     * Generate short name from official name
     */
    private function generateShortName(string $officialName): string
    {
        $words = explode(' ', $officialName);
        $shortName = '';

        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $shortName .= strtoupper(substr($word, 0, 1));
            }
        }

        return $shortName ?: substr($officialName, 0, 10);
    }

    /**
     * Generate academic year code
     */
    private function generateAcademicYearCode(string $name): string
    {
        // Extract year from name like "2025-2026" -> "AY2025"
        if (preg_match('/(\d{4})/', $name, $matches)) {
            return 'AY' . $matches[1];
        }

        return 'AY' . now()->year;
    }
}
