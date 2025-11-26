<?php

namespace App\Services\Assessment;

use App\Models\Assessment\AssessmentTerm;
use App\Models\Assessment\Assessment;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\Subject;
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

        // Get grade entries for the student in this term
        $gradeEntries = GradeEntry::where('student_id', $studentId)
            ->where('academic_term_id', $termId)
            ->get();

        // Get assessments for this term to match with grade entries
        $assessments = Assessment::where('term_id', $termId)
            ->with(['subject', 'type'])
            ->get()
            ->keyBy('id');

        // Group grade entries by subject
        $groupedBySubject = [];
        
        foreach ($gradeEntries as $entry) {
            // Find matching assessment by assessment_name
            $assessment = $assessments->first(function ($ass) use ($entry) {
                return $ass->title === $entry->assessment_name;
            });

            if (!$assessment) {
                continue;
            }

            $subjectId = $assessment->subject_id;
            
            if (!isset($groupedBySubject[$subjectId])) {
                $groupedBySubject[$subjectId] = [
                    'subject' => $assessment->subject,
                    'entries' => []
                ];
            }

            $groupedBySubject[$subjectId]['entries'][] = [
                'entry' => $entry,
                'assessment' => $assessment
            ];
        }

        // Build subjects array with grades
        $subjects = [];
        foreach ($groupedBySubject as $subjectId => $data) {
            $subjectGrades = [];
            $totalMarks = 0;
            $totalPossible = 0;
            $totalWeight = 0;
            $weightedSum = 0;

            foreach ($data['entries'] as $item) {
                $entry = $item['entry'];
                $assessment = $item['assessment'];
                
                $pointsEarned = $entry->points_earned ?? $entry->raw_score ?? 0;
                $pointsPossible = $entry->points_possible ?? $assessment->total_marks ?? 100;
                $weight = $entry->weight ?? $assessment->weight ?? 1.0;
                $percentage = $entry->percentage_score ?? ($pointsPossible > 0 ? ($pointsEarned / $pointsPossible) * 100 : 0);

                $subjectGrades[] = [
                    'assessment_id' => $assessment->id,
                    'assessment_title' => $assessment->title,
                    'score' => $pointsEarned,
                    'percentage' => round($percentage, 2),
                    'weight' => $weight,
                    'letter_grade' => $entry->letter_grade,
                ];

                $totalMarks += $pointsEarned;
                $totalPossible += $pointsPossible;
                $totalWeight += $weight;
                $weightedSum += $percentage * $weight;
            }

            $finalScore = $totalPossible > 0 ? ($totalMarks / $totalPossible) * 100 : 0;
            $finalPercentage = $totalWeight > 0 ? ($weightedSum / $totalWeight) : $finalScore;

            $subjects[] = [
                'subject_id' => $subjectId,
                'subject_name' => $data['subject']->name ?? 'Unknown',
                'assessments' => $subjectGrades,
                'final_score' => round($totalMarks, 2),
                'final_percentage' => round($finalPercentage, 2),
                'final_grade' => $this->calculateLetterGrade($finalPercentage),
            ];
        }

        // Calculate overall GPA and grade
        $overallGPA = $this->calculateOverallGPA($subjects);
        $overallGrade = $this->calculateOverallGrade($subjects);

        return [
            'student_id' => $studentId,
            'student_name' => $student->first_name . ' ' . $student->last_name,
            'term_id' => $termId,
            'term_name' => $term->name ?? 'Term ' . $termId,
            'subjects' => $subjects,
            'overall_gpa' => $overallGPA,
            'overall_grade' => $overallGrade,
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
            $percentage = $entry->percentage_score ?? 0;
            $totalPercentage += $percentage;
        }

        return round($totalPercentage / $gradeEntries->count(), 2);
    }

    protected function calculateLetterGrade(float $percentage): ?string
    {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 60) return 'D';
        return 'F';
    }

    protected function calculateOverallGPA(array $subjects): ?float
    {
        if (empty($subjects)) {
            return null;
        }

        $totalGPA = 0;
        $count = 0;

        foreach ($subjects as $subject) {
            $percentage = $subject['final_percentage'] ?? 0;
            $gpa = $this->percentageToGPA($percentage);
            if ($gpa !== null) {
                $totalGPA += $gpa;
                $count++;
            }
        }

        return $count > 0 ? round($totalGPA / $count, 2) : null;
    }

    protected function percentageToGPA(float $percentage): ?float
    {
        if ($percentage >= 90) return 4.0;
        if ($percentage >= 80) return 3.0;
        if ($percentage >= 70) return 2.0;
        if ($percentage >= 60) return 1.0;
        return 0.0;
    }

    protected function calculateOverallGrade(array $subjects): ?string
    {
        if (empty($subjects)) {
            return null;
        }

        $totalPercentage = 0;
        $count = 0;

        foreach ($subjects as $subject) {
            $percentage = $subject['final_percentage'] ?? 0;
            $totalPercentage += $percentage;
            $count++;
        }

        $averagePercentage = $count > 0 ? ($totalPercentage / $count) : 0;
        return $this->calculateLetterGrade($averagePercentage);
    }
}

