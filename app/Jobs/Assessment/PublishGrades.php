<?php

namespace App\Jobs\Assessment;

use App\Events\Assessment\GradesPublished;
use App\Models\Assessment\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PublishGrades implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Assessment $assessment
    ) {}

    public function handle(): void
    {
        DB::beginTransaction();

        try {
            // Update all grade entries for this assessment
            $gradeEntries = $this->assessment->gradeEntries()
                ->where('is_published', false)
                ->get();

            foreach ($gradeEntries as $gradeEntry) {
                $gradeEntry->update([
                    'is_published' => true,
                    'published_at' => now(),
                ]);
            }

            // Lock the assessment
            $this->assessment->update([
                'is_locked' => true,
                'published_at' => now(),
                'published_by' => auth()->id(),
            ]);

            DB::commit();

            // Dispatch event to notify students
            event(new GradesPublished($this->assessment, $gradeEntries));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

