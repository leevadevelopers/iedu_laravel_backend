<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\GradeLevel;
use App\Models\V1\Academic\GradeScale;
use Illuminate\Pagination\LengthAwarePaginator;

class GradeLevelService extends BaseAcademicService
{
    /**
     * Get grade levels with pagination and filters
     */
    public function getGradeLevels(array $filters = []): LengthAwarePaginator
    {
        $query = GradeLevel::with(['gradeScale'])
            ->whereHas('gradeScale', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            });

        // Apply filters
        if (isset($filters['grade_scale_id'])) {
            $query->where('grade_scale_id', $filters['grade_scale_id']);
        }

        if (isset($filters['is_passing'])) {
            $query->where('is_passing', $filters['is_passing']);
        }

        if (isset($filters['grade_value'])) {
            $query->where('grade_value', 'like', '%' . $filters['grade_value'] . '%');
        }

        if (isset($filters['min_percentage'])) {
            $query->where('percentage_min', '>=', $filters['min_percentage']);
        }

        if (isset($filters['max_percentage'])) {
            $query->where('percentage_max', '<=', $filters['max_percentage']);
        }

        // Order by sort_order by default
        $query->orderBy('sort_order');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a new grade level
     */
    public function createGradeLevel(array $data): GradeLevel
    {
        // Validate grade scale ownership
        $gradeScale = GradeScale::findOrFail($data['grade_scale_id']);
        $this->validateSchoolOwnership($gradeScale);

        // Set sort order if not provided
        if (!isset($data['sort_order'])) {
            $maxOrder = GradeLevel::where('grade_scale_id', $data['grade_scale_id'])
                ->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        return GradeLevel::create($data);
    }

    /**
     * Update a grade level
     */
    public function updateGradeLevel(GradeLevel $gradeLevel, array $data): GradeLevel
    {
        $this->validateGradeLevelOwnership($gradeLevel);

        $gradeLevel->update($data);
        return $gradeLevel->fresh();
    }

    /**
     * Delete a grade level
     */
    public function deleteGradeLevel(GradeLevel $gradeLevel): bool
    {
        $this->validateGradeLevelOwnership($gradeLevel);

        // Check if grade level is being used in grade entries
        if ($gradeLevel->gradeEntries()->count() > 0) {
            throw new \Exception('Cannot delete grade level that is being used in grade entries');
        }

        return $gradeLevel->delete();
    }

    /**
     * Get grade levels by grade scale
     */
    public function getGradeLevelsByGradeScale(int $gradeScaleId): \Illuminate\Database\Eloquent\Collection
    {
        $gradeScale = GradeScale::findOrFail($gradeScaleId);
        $this->validateSchoolOwnership($gradeScale);

        return GradeLevel::where('grade_scale_id', $gradeScaleId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get passing grade levels
     */
    public function getPassingGradeLevels(): \Illuminate\Database\Eloquent\Collection
    {
        return GradeLevel::with(['gradeScale'])
            ->whereHas('gradeScale', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            })
            ->where('is_passing', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get failing grade levels
     */
    public function getFailingGradeLevels(): \Illuminate\Database\Eloquent\Collection
    {
        return GradeLevel::with(['gradeScale'])
            ->whereHas('gradeScale', function ($q) {
                $q->where('school_id', $this->getCurrentSchoolId());
            })
            ->where('is_passing', false)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Reorder grade levels
     */
    public function reorderGradeLevels(array $gradeLevels): void
    {
        foreach ($gradeLevels as $gradeLevelData) {
            $gradeLevel = GradeLevel::findOrFail($gradeLevelData['id']);
            $this->validateGradeLevelOwnership($gradeLevel);

            $gradeLevel->update(['sort_order' => $gradeLevelData['sort_order']]);
        }
    }

    /**
     * Get grade for percentage
     */
    public function getGradeForPercentage(int $gradeScaleId, float $percentage): ?GradeLevel
    {
        $gradeScale = GradeScale::findOrFail($gradeScaleId);
        $this->validateSchoolOwnership($gradeScale);

        return $gradeScale->getGradeForPercentage($percentage);
    }

    /**
     * Validate grade level ownership through grade scale
     */
    protected function validateGradeLevelOwnership(GradeLevel $gradeLevel): void
    {
        $gradeScale = $gradeLevel->gradeScale;
        $this->validateSchoolOwnership($gradeScale);
    }

    /**
     * Create default grade levels for a grade scale
     */
    public function createDefaultGradeLevels(int $gradeScaleId, string $scaleType = 'letter'): array
    {
        $gradeScale = GradeScale::findOrFail($gradeScaleId);
        $this->validateSchoolOwnership($gradeScale);

        $defaultLevels = $this->getDefaultGradeLevelsByType($scaleType);
        $createdLevels = [];

        foreach ($defaultLevels as $index => $levelData) {
            $levelData['grade_scale_id'] = $gradeScaleId;
            $levelData['sort_order'] = $index + 1;

            $gradeLevel = GradeLevel::create($levelData);
            $createdLevels[] = $gradeLevel;
        }

        return $createdLevels;
    }

    /**
     * Get default grade levels by type
     */
    protected function getDefaultGradeLevelsByType(string $scaleType): array
    {
        $defaults = [
            'letter' => [
                ['grade_value' => 'A+', 'display_value' => 'A+', 'numeric_value' => 4.0, 'gpa_points' => 4.0, 'percentage_min' => 97, 'percentage_max' => 100, 'is_passing' => true, 'color_code' => '#28a745'],
                ['grade_value' => 'A', 'display_value' => 'A', 'numeric_value' => 4.0, 'gpa_points' => 4.0, 'percentage_min' => 93, 'percentage_max' => 96.99, 'is_passing' => true, 'color_code' => '#28a745'],
                ['grade_value' => 'A-', 'display_value' => 'A-', 'numeric_value' => 3.7, 'gpa_points' => 3.7, 'percentage_min' => 90, 'percentage_max' => 92.99, 'is_passing' => true, 'color_code' => '#28a745'],
                ['grade_value' => 'B+', 'display_value' => 'B+', 'numeric_value' => 3.3, 'gpa_points' => 3.3, 'percentage_min' => 87, 'percentage_max' => 89.99, 'is_passing' => true, 'color_code' => '#17a2b8'],
                ['grade_value' => 'B', 'display_value' => 'B', 'numeric_value' => 3.0, 'gpa_points' => 3.0, 'percentage_min' => 83, 'percentage_max' => 86.99, 'is_passing' => true, 'color_code' => '#17a2b8'],
                ['grade_value' => 'B-', 'display_value' => 'B-', 'numeric_value' => 2.7, 'gpa_points' => 2.7, 'percentage_min' => 80, 'percentage_max' => 82.99, 'is_passing' => true, 'color_code' => '#17a2b8'],
                ['grade_value' => 'C+', 'display_value' => 'C+', 'numeric_value' => 2.3, 'gpa_points' => 2.3, 'percentage_min' => 77, 'percentage_max' => 79.99, 'is_passing' => true, 'color_code' => '#ffc107'],
                ['grade_value' => 'C', 'display_value' => 'C', 'numeric_value' => 2.0, 'gpa_points' => 2.0, 'percentage_min' => 73, 'percentage_max' => 76.99, 'is_passing' => true, 'color_code' => '#ffc107'],
                ['grade_value' => 'C-', 'display_value' => 'C-', 'numeric_value' => 1.7, 'gpa_points' => 1.7, 'percentage_min' => 70, 'percentage_max' => 72.99, 'is_passing' => true, 'color_code' => '#ffc107'],
                ['grade_value' => 'D+', 'display_value' => 'D+', 'numeric_value' => 1.3, 'gpa_points' => 1.3, 'percentage_min' => 67, 'percentage_max' => 69.99, 'is_passing' => true, 'color_code' => '#fd7e14'],
                ['grade_value' => 'D', 'display_value' => 'D', 'numeric_value' => 1.0, 'gpa_points' => 1.0, 'percentage_min' => 63, 'percentage_max' => 66.99, 'is_passing' => true, 'color_code' => '#fd7e14'],
                ['grade_value' => 'D-', 'display_value' => 'D-', 'numeric_value' => 0.7, 'gpa_points' => 0.7, 'percentage_min' => 60, 'percentage_max' => 62.99, 'is_passing' => true, 'color_code' => '#fd7e14'],
                ['grade_value' => 'F', 'display_value' => 'F', 'numeric_value' => 0.0, 'gpa_points' => 0.0, 'percentage_min' => 0, 'percentage_max' => 59.99, 'is_passing' => false, 'color_code' => '#dc3545'],
            ],
            'numeric' => [
                ['grade_value' => '100', 'display_value' => '100', 'numeric_value' => 100, 'gpa_points' => 4.0, 'percentage_min' => 100, 'percentage_max' => 100, 'is_passing' => true, 'color_code' => '#28a745'],
                ['grade_value' => '95', 'display_value' => '95', 'numeric_value' => 95, 'gpa_points' => 3.8, 'percentage_min' => 95, 'percentage_max' => 99, 'is_passing' => true, 'color_code' => '#28a745'],
                ['grade_value' => '90', 'display_value' => '90', 'numeric_value' => 90, 'gpa_points' => 3.6, 'percentage_min' => 90, 'percentage_max' => 94, 'is_passing' => true, 'color_code' => '#28a745'],
                ['grade_value' => '85', 'display_value' => '85', 'numeric_value' => 85, 'gpa_points' => 3.4, 'percentage_min' => 85, 'percentage_max' => 89, 'is_passing' => true, 'color_code' => '#17a2b8'],
                ['grade_value' => '80', 'display_value' => '80', 'numeric_value' => 80, 'gpa_points' => 3.2, 'percentage_min' => 80, 'percentage_max' => 84, 'is_passing' => true, 'color_code' => '#17a2b8'],
                ['grade_value' => '75', 'display_value' => '75', 'numeric_value' => 75, 'gpa_points' => 3.0, 'percentage_min' => 75, 'percentage_max' => 79, 'is_passing' => true, 'color_code' => '#ffc107'],
                ['grade_value' => '70', 'display_value' => '70', 'numeric_value' => 70, 'gpa_points' => 2.8, 'percentage_min' => 70, 'percentage_max' => 74, 'is_passing' => true, 'color_code' => '#ffc107'],
                ['grade_value' => '65', 'display_value' => '65', 'numeric_value' => 65, 'gpa_points' => 2.6, 'percentage_min' => 65, 'percentage_max' => 69, 'is_passing' => true, 'color_code' => '#fd7e14'],
                ['grade_value' => '60', 'display_value' => '60', 'numeric_value' => 60, 'gpa_points' => 2.4, 'percentage_min' => 60, 'percentage_max' => 64, 'is_passing' => true, 'color_code' => '#fd7e14'],
                ['grade_value' => '0', 'display_value' => '0', 'numeric_value' => 0, 'gpa_points' => 0.0, 'percentage_min' => 0, 'percentage_max' => 59, 'is_passing' => false, 'color_code' => '#dc3545'],
            ]
        ];

        return $defaults[$scaleType] ?? $defaults['letter'];
    }

    /**
     * Validate grade level data
     */
    public function validateGradeLevelData(array $data): array
    {
        $errors = [];

        // Check if grade scale exists and belongs to school
        if (isset($data['grade_scale_id'])) {
            $gradeScale = GradeScale::where('id', $data['grade_scale_id'])
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if (!$gradeScale) {
                $errors['grade_scale_id'] = 'Invalid grade scale selected';
            }
        }

        // Validate percentage range
        if (isset($data['percentage_min']) && isset($data['percentage_max'])) {
            if ($data['percentage_min'] >= $data['percentage_max']) {
                $errors['percentage_max'] = 'Maximum percentage must be greater than minimum percentage';
            }
        }

        // Check for overlapping percentage ranges
        if (isset($data['grade_scale_id']) && isset($data['percentage_min']) && isset($data['percentage_max'])) {
            $overlapping = GradeLevel::where('grade_scale_id', $data['grade_scale_id'])
                ->where(function ($q) use ($data) {
                    $q->whereBetween('percentage_min', [$data['percentage_min'], $data['percentage_max']])
                      ->orWhereBetween('percentage_max', [$data['percentage_min'], $data['percentage_max']])
                      ->orWhere(function ($q2) use ($data) {
                          $q2->where('percentage_min', '<=', $data['percentage_min'])
                             ->where('percentage_max', '>=', $data['percentage_max']);
                      });
                })
                ->first();

            if ($overlapping) {
                $errors['percentage_range'] = 'Percentage range overlaps with existing grade level';
            }
        }

        return $errors;
    }
}
