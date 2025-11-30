<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\ImportReconciliationRequest;
use App\Http\Resources\Financial\ReconciliationImportResource;
use App\Models\V1\Financial\ReconciliationImport;
use App\Jobs\ProcessReconciliation;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReconciliationController extends BaseController
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Import reconciliation statement
     */
    public function import(ImportReconciliationRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            // Store file
            $file = $request->file('file');
            $filePath = $file->store('reconciliation', 'public');

            // Create import record
            $import = ReconciliationImport::create([
                'provider' => $request->provider,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'file_path' => $filePath,
                'status' => 'processing',
                'school_id' => $schoolId,
                'imported_by' => auth('api')->id(),
            ]);

            // Queue processing job
            ProcessReconciliation::dispatch($import);

            return $this->successResponse(
                new ReconciliationImportResource($import->load('importer')),
                'Reconciliation import started',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to import reconciliation: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get import status
     */
    public function getStatus(string $importId): JsonResponse
    {
        try {
            $import = ReconciliationImport::where('import_id', $importId)
                ->with(['importer', 'transactions.matchedStudent'])
                ->firstOrFail();

            return $this->successResponse(
                new ReconciliationImportResource($import),
                'Import status retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve import status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Confirm matches
     */
    public function confirmMatches(Request $request, string $importId): JsonResponse
    {
        $request->validate([
            'matches' => 'required|array',
            'matches.*.transaction_id' => 'required|string',
            'matches.*.student_id' => 'required|exists:students,id',
        ]);

        try {
            $import = ReconciliationImport::where('import_id', $importId)->firstOrFail();

            $reconciliationService = app(\App\Services\Reconciliation\ReconciliationService::class);
            $reconciliationService->confirmMatches($import, $request->matches);

            return $this->successResponse(
                new ReconciliationImportResource($import->fresh()->load(['importer', 'transactions'])),
                'Matches confirmed successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to confirm matches: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get current school ID helper
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }
}

