<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\GradeScale;
use App\Models\V1\Academic\GradingSystem;
use Illuminate\Pagination\LengthAwarePaginator;

class GradeScaleService extends BaseAcademicService
{
    /**
     * Get grade scales with pagination and filters
     */
    public function getGradeScales(array $filters = []): LengthAwarePaginator
    {
        $query = GradeScale::with(['gradeLevels', 'gradingSystem'])
            ->where('school_id', $this->getCurrentSchoolId());

        // Apply filters
        if (isset($filters['grading_system_id'])) {
            $query->where('grading_system_id', $filters['grading_system_id']);
        }

        if (isset($filters['scale_type'])) {
            $query->where('scale_type', $filters['scale_type']);
        }

        if (isset($filters['is_default'])) {
            $query->where('is_default', $filters['is_default']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a new grade scale
     */
    public function createGradeScale(array $data): GradeScale
    {
        $data['school_id'] = $this->getCurrentSchoolId();

        // Ensure only one default grade scale per grading system
        if (isset($data['is_default']) && $data['is_default']) {
            GradeScale::where('grading_system_id', $data['grading_system_id'])
                ->where('school_id', $this->getCurrentSchoolId())
                ->update(['is_default' => false]);
        }

        return GradeScale::create($data);
    }

    /**
     * Update a grade scale
     */
    public function updateGradeScale(GradeScale $gradeScale, array $data): GradeScale
    {
        $this->validateSchoolOwnership($gradeScale);

        // Handle default grade scale logic
        if (isset($data['is_default']) && $data['is_default']) {
            GradeScale::where('grading_system_id', $gradeScale->grading_system_id)
                ->where('school_id', $this->getCurrentSchoolId())
                ->where('id', '!=', $gradeScale->id)
                ->update(['is_default' => false]);
        }

        $gradeScale->update($data);
        return $gradeScale->fresh();
    }

    /**
     * Delete a grade scale
     */
    public function deleteGradeScale(GradeScale $gradeScale): bool
    {
        $this->validateSchoolOwnership($gradeScale);

        // Check if grade scale has grade levels
        if ($gradeScale->gradeLevels()->count() > 0) {
            throw new \Exception('Cannot delete grade scale with existing grade levels');
        }

        return $gradeScale->delete();
    }

    /**
     * Set grade scale as default
     */
    public function setAsDefault(GradeScale $gradeScale): GradeScale
    {
        $this->validateSchoolOwnership($gradeScale);

        // Remove default status from other grade scales in the same grading system
        GradeScale::where('grading_system_id', $gradeScale->grading_system_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('id', '!=', $gradeScale->id)
            ->update(['is_default' => false]);

        $gradeScale->update(['is_default' => true]);
        return $gradeScale->fresh();
    }

    /**
     * Get grade scales by type
     */
    public function getGradeScalesByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return GradeScale::with(['gradeLevels'])
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('scale_type', $type)
            ->get();
    }

    /**
     * Get default grade scale
     */
    public function getDefaultGradeScale(): ?GradeScale
    {
        return GradeScale::with(['gradeLevels', 'gradingSystem'])
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get grade for percentage
     */
    public function getGradeForPercentage(GradeScale $gradeScale, float $percentage): ?\App\Models\V1\Academic\GradeLevel
    {
        $this->validateSchoolOwnership($gradeScale);

        return $gradeScale->getGradeForPercentage($percentage);
    }

    /**
     * Get grade scales by grading system
     */
    public function getGradeScalesByGradingSystem(int $gradingSystemId): \Illuminate\Database\Eloquent\Collection
    {
        return GradeScale::with(['gradeLevels'])
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('grading_system_id', $gradingSystemId)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Duplicate grade scale
     */
    public function duplicateGradeScale(GradeScale $gradeScale, string $newName): GradeScale
    {
        $this->validateSchoolOwnership($gradeScale);

        $newGradeScale = $gradeScale->replicate();
        $newGradeScale->name = $newName;
        $newGradeScale->is_default = false;
        $newGradeScale->save();

        // Duplicate grade levels
        foreach ($gradeScale->gradeLevels as $gradeLevel) {
            $newGradeLevel = $gradeLevel->replicate();
            $newGradeLevel->grade_scale_id = $newGradeScale->id;
            $newGradeLevel->save();
        }

        return $newGradeScale->load(['gradeLevels']);
    }

    /**
     * Validate grade scale data
     */
    public function validateGradeScaleData(array $data): array
    {
        $errors = [];

        // Check if grading system exists and belongs to school
        if (isset($data['grading_system_id'])) {
            $gradingSystem = GradingSystem::where('id', $data['grading_system_id'])
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if (!$gradingSystem) {
                $errors['grading_system_id'] = 'Invalid grading system selected';
            }
        }

        // Check for duplicate name within grading system
        if (isset($data['name']) && isset($data['grading_system_id'])) {
            $existing = GradeScale::where('name', $data['name'])
                ->where('grading_system_id', $data['grading_system_id'])
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if ($existing) {
                $errors['name'] = 'Grade scale name already exists in this grading system';
            }
        }

        return $errors;
    }
}
