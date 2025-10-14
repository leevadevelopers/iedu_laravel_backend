<?php

namespace App\Services\Assessment;

use App\Models\V1\Academic\GradeScale;
use App\Models\V1\Academic\GradeScaleRange;
use App\Models\V1\Academic\GradingSystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GradeScaleService
{
    /**
     * Create a new grade scale with ranges
     */
    public function createGradeScale(array $data): GradeScale
    {
        return DB::transaction(function () use ($data) {
            $scaleData = [
                'grading_system_id' => $data['grading_system_id'],
                'school_id' => $data['school_id'] ?? Auth::user()->school_id,
                'tenant_id' => $data['tenant_id'] ?? session('tenant_id') ?? Auth::user()->tenant_id,
                'name' => $data['name'],
                'scale_type' => $data['scale_type'],
                'is_default' => $data['is_default'] ?? false,
            ];

            // If setting as default, unset other defaults in the same grading system
            if ($scaleData['is_default']) {
                GradeScale::where('grading_system_id', $scaleData['grading_system_id'])
                    ->where('school_id', $scaleData['school_id'])
                    ->update(['is_default' => false]);
            }

            $gradeScale = GradeScale::create($scaleData);

            // Create ranges if provided
            if (!empty($data['ranges'])) {
                foreach ($data['ranges'] as $index => $rangeData) {
                    GradeScaleRange::create([
                        'grade_scale_id' => $gradeScale->id,
                        'min_value' => $rangeData['min_value'],
                        'max_value' => $rangeData['max_value'],
                        'display_label' => $rangeData['display_label'],
                        'description' => $rangeData['description'] ?? null,
                        'color' => $rangeData['color'] ?? null,
                        'gpa_equivalent' => $rangeData['gpa_equivalent'] ?? null,
                        'is_passing' => $rangeData['is_passing'] ?? true,
                        'order' => $rangeData['order'] ?? $index,
                    ]);
                }
            }

            return $gradeScale->fresh('ranges');
        });
    }

    /**
     * Update a grade scale
     */
    public function updateGradeScale(GradeScale $gradeScale, array $data): GradeScale
    {
        return DB::transaction(function () use ($gradeScale, $data) {
            // If setting as default, unset other defaults
            if (($data['is_default'] ?? false) && !$gradeScale->is_default) {
                GradeScale::where('grading_system_id', $gradeScale->grading_system_id)
                    ->where('school_id', $gradeScale->school_id)
                    ->where('id', '!=', $gradeScale->id)
                    ->update(['is_default' => false]);
            }

            $gradeScale->update([
                'name' => $data['name'] ?? $gradeScale->name,
                'scale_type' => $data['scale_type'] ?? $gradeScale->scale_type,
                'is_default' => $data['is_default'] ?? $gradeScale->is_default,
            ]);

            return $gradeScale->fresh();
        });
    }

    /**
     * Delete a grade scale
     */
    public function deleteGradeScale(GradeScale $gradeScale): bool
    {
        if ($gradeScale->is_default) {
            throw new \Exception('Cannot delete default grade scale. Set another scale as default first.');
        }

        // Check if scale is in use
        $inUse = \App\Models\V1\Academic\GradeEntry::where('letter_grade', 'like', '%' . $gradeScale->name . '%')
            ->exists();

        if ($inUse) {
            throw new \Exception('Cannot delete grade scale that is currently in use.');
        }

        return $gradeScale->delete();
    }

    /**
     * Add or update a range in a grade scale
     */
    public function updateRange(GradeScale $gradeScale, array $rangeData, ?int $rangeId = null): GradeScaleRange
    {
        if ($rangeId) {
            $range = GradeScaleRange::findOrFail($rangeId);
            $range->update($rangeData);
            return $range;
        }

        return GradeScaleRange::create(array_merge($rangeData, [
            'grade_scale_id' => $gradeScale->id,
        ]));
    }

    /**
     * Delete a range
     */
    public function deleteRange(GradeScaleRange $range): bool
    {
        return $range->delete();
    }

    /**
     * Get the default scale for a school/tenant
     */
    public function getDefaultScale(int $schoolId, ?int $tenantId = null): ?GradeScale
    {
        $tenantId = $tenantId ?? session('tenant_id') ?? Auth::user()->tenant_id;

        return GradeScale::where('school_id', $schoolId)
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->with('ranges')
            ->first();
    }

    /**
     * Convert a score using a specific scale
     */
    public function convertScore(float $score, GradeScale $gradeScale): array
    {
        $gradeInfo = $gradeScale->convertScoreToGrade($score);

        if (!$gradeInfo) {
            return [
                'original_score' => $score,
                'scale_name' => $gradeScale->name,
                'scale_type' => $gradeScale->scale_type,
                'grade' => null,
                'is_passing' => false,
                'error' => 'Score out of range',
            ];
        }

        return [
            'original_score' => $score,
            'scale_name' => $gradeScale->name,
            'scale_type' => $gradeScale->scale_type,
            'grade' => $gradeInfo['label'],
            'description' => $gradeInfo['description'],
            'color' => $gradeInfo['color'],
            'gpa_equivalent' => $gradeInfo['gpa_equivalent'],
            'is_passing' => $gradeInfo['is_passing'],
        ];
    }

    /**
     * Convert between different scales
     */
    public function convertBetweenScales(float $score, GradeScale $fromScale, GradeScale $toScale): array
    {
        // First, normalize the score to percentage
        $percentage = $this->normalizeToPercentage($score, $fromScale);

        // Then convert to target scale
        $targetGrade = $toScale->convertFromPercentage($percentage);

        return [
            'from_scale' => $fromScale->name,
            'from_score' => $score,
            'percentage' => $percentage,
            'to_scale' => $toScale->name,
            'to_grade' => $targetGrade,
        ];
    }

    /**
     * Normalize any score to percentage (0-100)
     */
    protected function normalizeToPercentage(float $score, GradeScale $scale): float
    {
        if ($scale->scale_type === 'percentage') {
            return $score;
        }

        if ($scale->scale_type === 'points') {
            $maxPoints = $scale->ranges()->max('max_value');
            return ($score / $maxPoints) * 100;
        }

        if ($scale->scale_type === 'letter') {
            // Find the range and get its midpoint percentage
            $range = $scale->ranges()
                ->where('display_label', $score)
                ->first();

            if ($range) {
                return $range->getMidpoint();
            }
        }

        return 0;
    }

    /**
     * Get all scales for a grading system
     */
    public function getScalesForSystem(int $gradingSystemId): \Illuminate\Database\Eloquent\Collection
    {
        return GradeScale::where('grading_system_id', $gradingSystemId)
            ->with('ranges')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Calculate GPA for multiple grades
     */
    public function calculateGPA(array $grades, GradeScale $scale): float
    {
        $totalGPA = 0;
        $count = 0;

        foreach ($grades as $grade) {
            $gpa = $scale->getGPAEquivalent($grade['score']);
            if ($gpa !== null) {
                $totalGPA += $gpa * ($grade['weight'] ?? 1);
                $count += ($grade['weight'] ?? 1);
            }
        }

        return $count > 0 ? round($totalGPA / $count, 2) : 0;
    }

    /**
     * Validate that ranges don't overlap
     */
    public function validateRanges(array $ranges): array
    {
        $errors = [];

        // Sort ranges by min_value
        usort($ranges, fn($a, $b) => $a['min_value'] <=> $b['min_value']);

        for ($i = 0; $i < count($ranges) - 1; $i++) {
            if ($ranges[$i]['max_value'] > $ranges[$i + 1]['min_value']) {
                $errors[] = "Range overlap detected between {$ranges[$i]['display_label']} and {$ranges[$i + 1]['display_label']}";
            }
        }

        return $errors;
    }
}

