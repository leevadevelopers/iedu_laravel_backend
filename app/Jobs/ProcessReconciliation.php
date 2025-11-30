<?php

namespace App\Jobs;

use App\Models\V1\Financial\ReconciliationImport;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReconciliation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ReconciliationImport $import;

    public function __construct(ReconciliationImport $import)
    {
        $this->import = $import;
    }

    public function handle(ReconciliationService $reconciliationService): void
    {
        try {
            if (!$this->import->file_path) {
                throw new \Exception('File path not found for import');
            }

            $reconciliationService->processImport($this->import, $this->import->file_path);
        } catch (\Exception $e) {
            Log::error('Reconciliation processing failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            $this->import->update([
                'status' => 'failed',
                'metadata' => array_merge($this->import->metadata ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]),
            ]);

            throw $e;
        }
    }
}

