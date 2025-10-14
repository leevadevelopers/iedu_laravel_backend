<?php

namespace App\Jobs\Assessment;

use App\Models\Assessment\Gradebook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateGradebookReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Gradebook $gradebook,
        public string $format = 'pdf' // pdf, csv, xlsx
    ) {}

    public function handle(): void
    {
        $grades = $this->gradebook->term
            ->assessments()
            ->where('subject_id', $this->gradebook->subject_id)
            ->where('class_id', $this->gradebook->class_id)
            ->with(['gradeEntries.student'])
            ->get();

        $data = [
            'gradebook' => $this->gradebook,
            'grades' => $grades,
            'generated_at' => now(),
        ];

        if ($this->format === 'pdf') {
            $pdf = Pdf::loadView('reports.gradebook', $data);
            $filename = 'gradebook_' . $this->gradebook->id . '_' . now()->timestamp . '.pdf';
            $path = 'gradebooks/' . $filename;
            
            Storage::put($path, $pdf->output());
            
            $this->gradebook->update(['file_path' => $path]);
        }
        
        // Additional formats can be implemented here (CSV, XLSX)
    }
}

