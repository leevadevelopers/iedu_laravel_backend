<?php

namespace App\Jobs\Assessment;

use App\Models\User;
use App\Models\Assessment\AssessmentTerm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateStudentGPA implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $student,
        public AssessmentTerm $term
    ) {}

    public function handle(): void
    {
        // Get all published grades for this student in this term
        $gradeEntries = $this->term->assessments()
            ->with(['gradeEntries' => function ($query) {
                $query->where('student_id', $this->student->id)
                      ->where('is_published', true)
                      ->where('is_final', true);
            }])
            ->get()
            ->pluck('gradeEntries')
            ->flatten();

        if ($gradeEntries->isEmpty()) {
            return;
        }

        // Calculate weighted average
        $totalWeightedMarks = 0;
        $totalWeight = 0;

        foreach ($gradeEntries as $entry) {
            $assessment = $entry->assessment;
            $weight = $assessment->weight ?: 1;
            $percentage = ($entry->marks_awarded / $assessment->total_marks) * 100;
            
            $totalWeightedMarks += $percentage * $weight;
            $totalWeight += $weight;
        }

        $gpa = $totalWeight > 0 ? $totalWeightedMarks / $totalWeight : 0;

        // Store GPA in student metadata or separate table
        $this->student->update([
            'metadata' => array_merge($this->student->metadata ?? [], [
                'gpa_term_' . $this->term->id => round($gpa, 2),
            ]),
        ]);
    }
}

