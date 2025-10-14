<?php

namespace App\Jobs\Assessment;

use App\Models\Assessment\Assessment;
use App\Models\Assessment\GradeEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBulkGradeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Assessment $assessment,
        public array $gradesData,
        public int $userId
    ) {}

    public function handle(): void
    {
        DB::beginTransaction();

        try {
            foreach ($this->gradesData as $gradeData) {
                GradeEntry::updateOrCreate(
                    [
                        'tenant_id' => $this->assessment->tenant_id,
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $gradeData['student_id'],
                        'component_id' => $gradeData['component_id'] ?? null,
                    ],
                    [
                        'marks_awarded' => $gradeData['marks_awarded'],
                        'grade_value' => $gradeData['grade_value'] ?? null,
                        'remarks' => $gradeData['remarks'] ?? null,
                        'entered_by' => $this->userId,
                        'entered_at' => now(),
                    ]
                );
            }

            DB::commit();
            
            Log::info('Bulk grade import completed', [
                'assessment_id' => $this->assessment->id,
                'grades_count' => count($this->gradesData),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk grade import failed', [
                'assessment_id' => $this->assessment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

