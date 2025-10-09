<?php

namespace App\Services\Assessment;

use App\Events\Assessment\AssessmentCreated;
use App\Events\Assessment\AssessmentUpdated;
use App\Models\Assessment\Assessment;
use App\Models\Assessment\AssessmentComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssessmentService
{
    public function createAssessment(array $data): Assessment
    {
        return DB::transaction(function () use ($data) {
            $assessmentData = array_merge($data, [
                'tenant_id' => session('tenant_id') ?? Auth::user()->tenant_id,
                'teacher_id' => $data['teacher_id'] ?? Auth::id(),
            ]);

            // Extract components if present
            $components = $assessmentData['components'] ?? [];
            unset($assessmentData['components']);

            $assessment = Assessment::create($assessmentData);

            // Create components if provided
            if (!empty($components)) {
                foreach ($components as $index => $componentData) {
                    AssessmentComponent::create([
                        'assessment_id' => $assessment->id,
                        'name' => $componentData['name'],
                        'description' => $componentData['description'] ?? null,
                        'weight_pct' => $componentData['weight_pct'],
                        'max_marks' => $componentData['max_marks'],
                        'rubric' => $componentData['rubric'] ?? null,
                        'order' => $index,
                    ]);
                }
            }

            event(new AssessmentCreated($assessment));

            return $assessment->fresh(['components', 'type', 'term']);
        });
    }

    public function updateAssessment(Assessment $assessment, array $data): Assessment
    {
        return DB::transaction(function () use ($assessment, $data) {
            // Check if assessment is locked
            if ($assessment->is_locked) {
                throw new \Exception('Cannot update locked assessment');
            }

            $assessment->update($data);

            event(new AssessmentUpdated($assessment));

            return $assessment->fresh();
        });
    }

    public function deleteAssessment(Assessment $assessment): bool
    {
        if ($assessment->is_locked) {
            throw new \Exception('Cannot delete locked assessment');
        }

        if ($assessment->gradeEntries()->exists()) {
            throw new \Exception('Cannot delete assessment with existing grades');
        }

        return $assessment->delete();
    }

    public function changeStatus(Assessment $assessment, string $status): Assessment
    {
        $assessment->update(['status' => $status]);
        
        event(new AssessmentUpdated($assessment));
        
        return $assessment->fresh();
    }

    public function lockAssessment(Assessment $assessment): Assessment
    {
        $assessment->update([
            'is_locked' => true,
            'published_at' => now(),
            'published_by' => Auth::id(),
        ]);

        return $assessment;
    }
}

