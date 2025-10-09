<?php

namespace App\Services\Assessment;

use App\Models\Assessment\AssessmentTerm;
use App\Models\User;
use Illuminate\Support\Collection;

class ReportService
{
    public function getClassGradesSummary(int $classId, int $termId): array
    {
        $term = AssessmentTerm::with([
            'assessments' => function ($query) use ($classId) {
                $query->where('class_id', $classId)
                      ->with(['gradeEntries.student', 'subject']);
            }
        ])->findOrFail($termId);

        $assessments = $term->assessments;
        $students = [];

        foreach ($assessments as $assessment) {
            foreach ($assessment->gradeEntries as $entry) {
                if (!isset($students[$entry->student_id])) {
                    $students[$entry->student_id] = [
                        'student' => $entry->student,
                        'grades' => [],
                        'average' => 0,
                    ];
                }

                $students[$entry->student_id]['grades'][$assessment->id] = [
                    'subject' => $assessment->subject->name,
                    'marks' => $entry->marks_awarded,
                    'total' => $assessment->total_marks,
                    'percentage' => ($entry->marks_awarded / $assessment->total_marks) * 100,
                ];
            }
        }

        // Calculate averages
        foreach ($students as $studentId => &$studentData) {
            $totalPercentage = 0;
            $count = count($studentData['grades']);
            
            foreach ($studentData['grades'] as $grade) {
                $totalPercentage += $grade['percentage'];
            }
            
            $studentData['average'] = $count > 0 ? round($totalPercentage / $count, 2) : 0;
        }

        return [
            'term' => $term,
            'class_id' => $classId,
            'students' => array_values($students),
            'summary' => [
                'total_students' => count($students),
                'total_assessments' => $assessments->count(),
                'class_average' => $this->calculateClassAverage($students),
            ],
        ];
    }

    public function getStudentTranscript(int $studentId, int $termId): array
    {
        $student = User::findOrFail($studentId);
        $term = AssessmentTerm::findOrFail($termId);

        $gradeEntries = \App\Models\Assessment\GradeEntry::where('student_id', $studentId)
            ->where('is_published', true)
            ->whereHas('assessment', function ($query) use ($termId) {
                $query->where('term_id', $termId);
            })
            ->with(['assessment.subject', 'assessment.type'])
            ->get();

        $groupedBySubject = $gradeEntries->groupBy('assessment.subject_id');

        $subjects = [];
        foreach ($groupedBySubject as $subjectId => $entries) {
            $subjectGrades = [];
            $totalMarks = 0;
            $totalPossible = 0;

            foreach ($entries as $entry) {
                $subjectGrades[] = [
                    'assessment' => $entry->assessment->title,
                    'type' => $entry->assessment->type->name,
                    'marks' => $entry->marks_awarded,
                    'total' => $entry->assessment->total_marks,
                    'percentage' => ($entry->marks_awarded / $entry->assessment->total_marks) * 100,
                ];

                $totalMarks += $entry->marks_awarded;
                $totalPossible += $entry->assessment->total_marks;
            }

            $subjects[] = [
                'subject' => $entries->first()->assessment->subject->name,
                'grades' => $subjectGrades,
                'average' => $totalPossible > 0 ? round(($totalMarks / $totalPossible) * 100, 2) : 0,
            ];
        }

        return [
            'student' => $student,
            'term' => $term,
            'subjects' => $subjects,
            'overall_average' => $this->calculateOverallAverage($gradeEntries),
        ];
    }

    protected function calculateClassAverage(array $students): float
    {
        if (empty($students)) {
            return 0;
        }

        $totalAverage = 0;
        foreach ($students as $student) {
            $totalAverage += $student['average'];
        }

        return round($totalAverage / count($students), 2);
    }

    protected function calculateOverallAverage(Collection $gradeEntries): float
    {
        if ($gradeEntries->isEmpty()) {
            return 0;
        }

        $totalPercentage = 0;
        foreach ($gradeEntries as $entry) {
            $totalPercentage += ($entry->marks_awarded / $entry->assessment->total_marks) * 100;
        }

        return round($totalPercentage / $gradeEntries->count(), 2);
    }
}

