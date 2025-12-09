<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\GradingSystem;
use App\Models\V1\Academic\GradeScale;
use App\Models\V1\Academic\GradeLevel;
use App\Models\V1\SIS\School\SchoolUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GradingSystemService extends BaseAcademicService
{
    public function __construct()
    {
        // No longer using repositories
    }

    /**
     * Get grading systems with filters
     */
    public function getGradingSystems(array $filters = [])
    {
        $user = Auth::user();

        $schoolUser = SchoolUser::where('user_id', $user->id)->first();
        if (!$schoolUser) {
            throw new \Exception('User is not associated with any school');
        }

        $schoolId = $schoolUser->school_id;
        $query = GradingSystem::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId);

        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['system_type'])) {
            $query->where('system_type', $filters['system_type']);
        }

        if (isset($filters['is_primary'])) {
            $query->where('is_primary', $filters['is_primary']);
        }

        return $query->with(['gradeScales.gradeLevels', 'school'])
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create new grading system
     */
    public function createGradingSystem(array $data): GradingSystem
    {
        $user = Auth::user();

        // Add tenant_id and school_id from authenticated user
        $data['tenant_id'] = $user->tenant_id;
        $data['school_id'] = $this->getCurrentSchoolId();

        $gradingSystem = GradingSystem::create($data);

        // Create default grade scale
        $this->createDefaultGradeScale($gradingSystem);

        return $gradingSystem->load('gradeScales.ranges');
    }

    /**
     * Update grading system
     */
    public function updateGradingSystem(GradingSystem $gradingSystem, array $data)
    {
        $this->validateTenantAndSchoolOwnership($gradingSystem);

        $gradingSystem->update($data);
        return $gradingSystem->fresh();
    }

    /**
     * Delete grading system
     */
    public function deleteGradingSystem(GradingSystem $gradingSystem): bool
    {
        // Load relationships explicitly
        $gradingSystem->load(['gradeScales.ranges']);

        $this->validateTenantAndSchoolOwnership($gradingSystem);

        // Check if it's the primary system
        if ($gradingSystem->is_primary) {
            throw new \Exception('Cannot delete primary grading system');
        }

        // Check for dependencies (grade entries using this system)
        if ($this->hasGradingSystemDependencies($gradingSystem)) {
            throw new \Exception('Cannot delete grading system with existing grade entries');
        }

        return $gradingSystem->delete();
    }

    /**
     * Get primary grading system
     */
    public function getPrimaryGradingSystem(): ?GradingSystem
    {
        $user = Auth::user();

        return GradingSystem::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('is_primary', true)
            ->with(['gradeScales.ranges'])
            ->first();
    }

    /**
     * Set grading system as primary
     */
    public function setPrimaryGradingSystem(GradingSystem $gradingSystem): GradingSystem
    {
        $this->validateTenantAndSchoolOwnership($gradingSystem);

        // Remove primary flag from other systems
        $user = Auth::user();
        GradingSystem::where('tenant_id', $user->tenant_id)
            ->where('school_id', $gradingSystem->school_id)
            ->where('id', '!=', $gradingSystem->id)
            ->update(['is_primary' => false]);

        $gradingSystem->update(['is_primary' => true]);
        return $gradingSystem->fresh();
    }

    /**
     * Create grade scale for grading system
     */
    public function createGradeScale(GradingSystem $gradingSystem, array $data): GradeScale
    {
        $this->validateTenantAndSchoolOwnership($gradingSystem);

        $user = Auth::user();
        $data['grading_system_id'] = $gradingSystem->id;
        $data['school_id'] = $gradingSystem->school_id;
        $data['tenant_id'] = $user->tenant_id;

        $gradeScale = GradeScale::create($data);

        // Create default grade levels
        $this->createDefaultGradeLevels($gradeScale);

        return $gradeScale->load('gradeLevels');
    }

    /**
     * Get grade for percentage
     */
    public function getGradeForPercentage(float $percentage, ?int $gradeScaleId = null): ?GradeLevel
    {
        if (!$gradeScaleId) {
            $primarySystem = $this->getPrimaryGradingSystem();
            if (!$primarySystem || !$primarySystem->gradeScales->isNotEmpty()) {
                return null;
            }
            $gradeScale = $primarySystem->gradeScales->where('is_default', true)->first()
                        ?? $primarySystem->gradeScales->first();
        } else {
            $gradeScale = GradeScale::find($gradeScaleId);
        }

        if (!$gradeScale) {
            return null;
        }

        return $gradeScale->getGradeForPercentage($percentage);
    }

    /**
     * Calculate GPA for grades
     */
    public function calculateGPA(array $gradeEntries, ?int $gradeScaleId = null): float
    {
        if (empty($gradeEntries)) {
            return 0.0;
        }

        $totalPoints = 0;
        $totalCredits = 0;

        foreach ($gradeEntries as $entry) {
            $gradeLevel = $this->getGradeForPercentage($entry['percentage'], $gradeScaleId);
            if ($gradeLevel && $gradeLevel->gpa_points !== null) {
                $credits = $entry['credits'] ?? 1.0;
                $totalPoints += $gradeLevel->gpa_points * $credits;
                $totalCredits += $credits;
            }
        }

        return $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;
    }

    /**
     * Create default grade scale for grading system
     */
    private function createDefaultGradeScale(GradingSystem $gradingSystem): void
    {
        $scaleName = match ($gradingSystem->system_type) {
            'traditional_letter' => 'Standard Letter Grades',
            'percentage' => 'Percentage Scale',
            'points' => 'Points Scale',
            'standards_based' => 'Standards-Based Scale',
            'narrative' => 'Narrative Assessment',
            default => 'Default Scale'
        };

        // Disable model events temporarily to avoid recursion
        GradeScale::unsetEventDispatcher();

        $gradeScale = GradeScale::create([
            'grading_system_id' => $gradingSystem->id,
            'school_id' => $gradingSystem->school_id,
            'tenant_id' => $gradingSystem->tenant_id,
            'name' => $scaleName,
            'scale_type' => $gradingSystem->system_type === 'traditional_letter' ? 'letter' : $gradingSystem->system_type,
            'is_default' => true
        ]);

        $this->createDefaultGradeLevels($gradeScale);
    }

    /**
     * Create default grade levels for grade scale
     */
    private function createDefaultGradeLevels(GradeScale $gradeScale): void
    {
        $gradeLevels = match ($gradeScale->scale_type) {
            'letter' => $this->getTraditionalLetterGrades(),
            'percentage' => $this->getPercentageGrades(),
            'standards' => $this->getStandardsBasedGrades(),
            default => $this->getTraditionalLetterGrades()
        };

        // Disable model events temporarily to avoid recursion
        GradeLevel::unsetEventDispatcher();

        foreach ($gradeLevels as $index => $level) {
            GradeLevel::create([
                'grade_scale_id' => $gradeScale->id,
                'school_id' => $gradeScale->school_id,
                'tenant_id' => $gradeScale->tenant_id,
                'grade_value' => $level['value'],
                'display_value' => $level['display'],
                'numeric_value' => $level['numeric'],
                'gpa_points' => $level['gpa'] ?? null,
                'percentage_min' => $level['min_percent'] ?? null,
                'percentage_max' => $level['max_percent'] ?? null,
                'description' => $level['description'] ?? null,
                'color_code' => $level['color'] ?? null,
                'is_passing' => $level['passing'] ?? true,
                'sort_order' => $index + 1
            ]);
        }
    }

    /**
     * Get traditional letter grade definitions
     */
    private function getTraditionalLetterGrades(): array
    {
        return [
            ['value' => 'A+', 'display' => 'A+', 'numeric' => 97.0, 'gpa' => 4.0, 'min_percent' => 97.0, 'max_percent' => 100.0, 'color' => '#2ECC40', 'passing' => true],
            ['value' => 'A', 'display' => 'A', 'numeric' => 95.0, 'gpa' => 4.0, 'min_percent' => 93.0, 'max_percent' => 96.9, 'color' => '#2ECC40', 'passing' => true],
            ['value' => 'A-', 'display' => 'A-', 'numeric' => 92.0, 'gpa' => 3.7, 'min_percent' => 90.0, 'max_percent' => 92.9, 'color' => '#2ECC40', 'passing' => true],
            ['value' => 'B+', 'display' => 'B+', 'numeric' => 89.0, 'gpa' => 3.3, 'min_percent' => 87.0, 'max_percent' => 89.9, 'color' => '#01FF70', 'passing' => true],
            ['value' => 'B', 'display' => 'B', 'numeric' => 85.0, 'gpa' => 3.0, 'min_percent' => 83.0, 'max_percent' => 86.9, 'color' => '#01FF70', 'passing' => true],
            ['value' => 'B-', 'display' => 'B-', 'numeric' => 82.0, 'gpa' => 2.7, 'min_percent' => 80.0, 'max_percent' => 82.9, 'color' => '#01FF70', 'passing' => true],
            ['value' => 'C+', 'display' => 'C+', 'numeric' => 79.0, 'gpa' => 2.3, 'min_percent' => 77.0, 'max_percent' => 79.9, 'color' => '#FFDC00', 'passing' => true],
            ['value' => 'C', 'display' => 'C', 'numeric' => 75.0, 'gpa' => 2.0, 'min_percent' => 73.0, 'max_percent' => 76.9, 'color' => '#FFDC00', 'passing' => true],
            ['value' => 'C-', 'display' => 'C-', 'numeric' => 72.0, 'gpa' => 1.7, 'min_percent' => 70.0, 'max_percent' => 72.9, 'color' => '#FFDC00', 'passing' => true],
            ['value' => 'D+', 'display' => 'D+', 'numeric' => 69.0, 'gpa' => 1.3, 'min_percent' => 67.0, 'max_percent' => 69.9, 'color' => '#FF851B', 'passing' => true],
            ['value' => 'D', 'display' => 'D', 'numeric' => 65.0, 'gpa' => 1.0, 'min_percent' => 60.0, 'max_percent' => 66.9, 'color' => '#FF851B', 'passing' => true],
            ['value' => 'F', 'display' => 'F', 'numeric' => 0.0, 'gpa' => 0.0, 'min_percent' => 0.0, 'max_percent' => 59.9, 'color' => '#FF4136', 'passing' => false]
        ];
    }

    /**
     * Get percentage grade definitions
     */
    private function getPercentageGrades(): array
    {
        $grades = [];
        for ($i = 100; $i >= 0; $i -= 10) {
            $max = min($i + 9, 100);
            $grades[] = [
                'value' => (string)$i,
                'display' => "{$i}-{$max}%",
                'numeric' => (float)$i,
                'min_percent' => (float)$i,
                'max_percent' => (float)$max,
                'passing' => $i >= 60
            ];
        }
        return $grades;
    }

    /**
     * Get standards-based grade definitions
     */
    private function getStandardsBasedGrades(): array
    {
        return [
            ['value' => '4', 'display' => 'Exceeds Standards', 'numeric' => 4.0, 'gpa' => 4.0, 'color' => '#2ECC40', 'passing' => true],
            ['value' => '3', 'display' => 'Meets Standards', 'numeric' => 3.0, 'gpa' => 3.0, 'color' => '#01FF70', 'passing' => true],
            ['value' => '2', 'display' => 'Approaching Standards', 'numeric' => 2.0, 'gpa' => 2.0, 'color' => '#FFDC00', 'passing' => true],
            ['value' => '1', 'display' => 'Below Standards', 'numeric' => 1.0, 'gpa' => 1.0, 'color' => '#FF4136', 'passing' => false]
        ];
    }

    /**
     * Check if grading system has dependencies
     */
    private function hasGradingSystemDependencies(GradingSystem $gradingSystem): bool
    {
        // Check for grade entries using this system's scales
        foreach ($gradingSystem->gradeScales as $scale) {
            foreach ($scale->gradeLevels as $level) {
                if (\App\Models\V1\Academic\GradeEntry::where('letter_grade', $level->grade_value)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get maximum score from the default grade scale
     * Returns the max_value from the default grade scale (no longer uses grading_systems)
     */
    public function getMaxScore(): ?float
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        // Get default grade scale directly (no longer through grading system)
        $defaultScale = \App\Models\V1\Academic\GradeScale::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('is_default', true)
            ->first();

        // If no default scale, get the first scale
        if (!$defaultScale) {
            $defaultScale = \App\Models\V1\Academic\GradeScale::where('tenant_id', $user->tenant_id)
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();
        }

        if (!$defaultScale) {
            // Return default based on common scale types
            return 100.0; // Default to percentage scale max
        }

        // Get max_value directly from grade scale
        if ($defaultScale->max_value) {
            return (float) $defaultScale->max_value;
        }

        // Get the maximum value from all ranges
        $maxValue = $defaultScale->ranges()->max('max_value');

        // If no ranges, check grade levels
        if (!$maxValue) {
            $maxValue = $defaultScale->gradeLevels()->max('percentage_max');
        }

        // If still no value, use scale type defaults
        if (!$maxValue) {
            $maxValue = match ($defaultScale->scale_type) {
                'percentage' => 100.0,
                'points' => 20.0, // Default for Portuguese system
                default => 100.0
            };
        }

        return $maxValue ? (float) $maxValue : null;
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

        $currentSchoolId = $this->getCurrentSchoolId();

        if ($model->school_id !== $currentSchoolId) {
            throw new \Exception('Access denied: Resource does not belong to current school');
        }
    }
}
