<?php

namespace App\Services\Assessment;

use App\Events\Assessment\GradeEntered;
use App\Events\Assessment\GradesPublished;
use App\Models\Assessment\Assessment;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\GradeScale;
use App\Models\Assessment\GradesAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class GradeService
{
    public function enterGrade(array $data): GradeEntry
    {
        return DB::transaction(function () use ($data) {
            // Map new fields to existing grade_entries table structure
            $schoolId = Auth::user()->school_id ?? $data['school_id'] ?? 1;
            $pointsEarned = $data['marks_awarded'] ?? $data['points_earned'];
            $pointsPossible = $data['total_marks'] ?? $data['points_possible'] ?? 100;
            
            // Calculate percentage
            $percentageScore = $pointsPossible > 0 ? ($pointsEarned / $pointsPossible) * 100 : 0;
            
            // Get default grade scale and convert
            $letterGrade = $data['grade_value'] ?? $data['letter_grade'] ?? null;
            if (!$letterGrade && $data['use_grade_scale'] ?? true) {
                $gradeScale = GradeScale::where('school_id', $schoolId)
                    ->where('is_default', true)
                    ->with('ranges')
                    ->first();
                    
                if ($gradeScale) {
                    $letterGrade = $gradeScale->getGradeLabel($percentageScore);
                }
            }
            
            $gradeData = [
                'tenant_id' => session('tenant_id') ?? Auth::user()->tenant_id,
                'school_id' => $schoolId,
                'student_id' => $data['student_id'],
                'class_id' => $data['class_id'] ?? Assessment::find($data['assessment_id'])->class_id,
                'academic_term_id' => $data['academic_term_id'] ?? Assessment::find($data['assessment_id'])->term->academic_term_id,
                'assessment_name' => $data['assessment_name'] ?? Assessment::find($data['assessment_id'])->title,
                'assessment_type' => $data['assessment_type'] ?? 'summative',
                'assessment_date' => $data['assessment_date'] ?? now(),
                'points_earned' => $pointsEarned,
                'points_possible' => $pointsPossible,
                'percentage_score' => $percentageScore,
                'letter_grade' => $letterGrade,
                'grade_category' => $data['grade_category'] ?? null,
                'weight' => $data['weight'] ?? 1.0,
                'teacher_comments' => $data['remarks'] ?? $data['teacher_comments'] ?? null,
                'private_notes' => $data['private_notes'] ?? null,
                'entered_by' => Auth::id(),
            ];

            $gradeEntry = GradeEntry::create($gradeData);

            // Log the change
            $this->logGradeChange($gradeEntry, 'created', null, $gradeEntry->points_earned, 'Initial entry');

            event(new GradeEntered($gradeEntry));

            return $gradeEntry->fresh(['student', 'enteredBy']);
        });
    }

    public function updateGrade(GradeEntry $gradeEntry, array $data): GradeEntry
    {
        return DB::transaction(function () use ($gradeEntry, $data) {
            $oldMarks = $gradeEntry->points_earned;
            
            $updateData = [
                'points_earned' => $data['marks_awarded'] ?? $data['points_earned'] ?? $gradeEntry->points_earned,
                'percentage_score' => $data['percentage_score'] ?? $gradeEntry->percentage_score,
                'letter_grade' => $data['grade_value'] ?? $data['letter_grade'] ?? $gradeEntry->letter_grade,
                'teacher_comments' => $data['remarks'] ?? $data['teacher_comments'] ?? $gradeEntry->teacher_comments,
                'private_notes' => $data['private_notes'] ?? $gradeEntry->private_notes,
                'modified_by' => Auth::id(),
            ];
            
            $gradeEntry->update($updateData);

            // Log the change
            $this->logGradeChange(
                $gradeEntry,
                'updated',
                $oldMarks,
                $gradeEntry->points_earned,
                $data['reason'] ?? 'Grade updated'
            );

            return $gradeEntry->fresh();
        });
    }

    public function publishGrades(Assessment $assessment): Collection
    {
        return DB::transaction(function () use ($assessment) {
            $gradeEntries = $assessment->gradeEntries()->get();

            foreach ($gradeEntries as $gradeEntry) {
                $this->logGradeChange($gradeEntry, 'published', null, null, 'Grades published');
            }

            $assessment->update([
                'is_locked' => true,
                'published_at' => now(),
                'published_by' => Auth::id(),
            ]);

            event(new GradesPublished($assessment, $gradeEntries));

            return $gradeEntries;
        });
    }

    public function calculateWeightedAverage(int $studentId, int $termId): float
    {
        $gradeEntries = GradeEntry::where('student_id', $studentId)
            ->where('academic_term_id', $termId)
            ->get();

        if ($gradeEntries->isEmpty()) {
            return 0;
        }

        $totalWeightedMarks = 0;
        $totalWeight = 0;

        foreach ($gradeEntries as $entry) {
            $weight = $entry->weight ?: 1;
            $percentage = $entry->calculatePercentage();
            
            $totalWeightedMarks += $percentage * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round($totalWeightedMarks / $totalWeight, 2) : 0;
    }

    public function convertToGradeScale(float $marks, int $gradeScaleId): ?string
    {
        $gradeScale = GradeScale::with('ranges')->find($gradeScaleId);
        
        if (!$gradeScale) {
            return null;
        }

        return $gradeScale->getGradeLabel($marks);
    }
    
    /**
     * Get detailed grade information with scale
     */
    public function getGradeWithScale(float $score, int $gradeScaleId): ?array
    {
        $gradeScale = GradeScale::with('ranges')->find($gradeScaleId);
        
        if (!$gradeScale) {
            return null;
        }

        return $gradeScale->convertScoreToGrade($score);
    }
    
    /**
     * Apply grade scale to existing grade entry
     */
    public function applyGradeScale(GradeEntry $gradeEntry, int $gradeScaleId): GradeEntry
    {
        $gradeScale = GradeScale::with('ranges')->findOrFail($gradeScaleId);
        
        $percentage = $gradeEntry->calculatePercentage();
        $letterGrade = $gradeScale->getGradeLabel($percentage);
        
        $gradeEntry->update([
            'letter_grade' => $letterGrade,
            'modified_by' => Auth::id(),
        ]);
        
        return $gradeEntry;
    }

    protected function logGradeChange(
        GradeEntry $gradeEntry,
        string $action,
        ?float $oldValue,
        ?float $newValue,
        string $reason
    ): void {
        GradesAuditLog::create([
            'grade_entry_id' => $gradeEntry->id,
            'changed_by' => Auth::id(),
            'action' => $action,
            'field_name' => 'marks_awarded',
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}

