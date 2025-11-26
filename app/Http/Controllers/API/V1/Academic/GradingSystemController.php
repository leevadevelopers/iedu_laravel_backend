<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradingSystem;
use App\Http\Requests\Academic\StoreGradingSystemRequest;
use App\Http\Requests\Academic\UpdateGradingSystemRequest;
use App\Services\V1\Academic\GradingSystemService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GradingSystemController extends Controller
{
    use ApiResponseTrait;
    protected GradingSystemService $gradingSystemService;

    public function __construct(GradingSystemService $gradingSystemService)
    {
        $this->gradingSystemService = $gradingSystemService;
    }

    /**
     * Display a listing of grading systems
     */
    public function index(Request $request): JsonResponse
    {
        $gradingSystems = $this->gradingSystemService->getGradingSystems($request->all());

        return $this->successPaginatedResponse($gradingSystems);
    }

    /**
     * Store a newly created grading system
     */
    public function store(StoreGradingSystemRequest $request): JsonResponse
    {
        try {
            $gradingSystem = $this->gradingSystemService->createGradingSystem($request->validated());

            return $this->successResponse($gradingSystem, 'Grading system created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create grading system', null, 'CREATE_ERROR', 422);
        }
    }

    /**
     * Display the specified grading system
     */
    public function show($id): JsonResponse
    {
        // Debug: Check if the grading system exists without tenant scope
        $debugGradingSystem = GradingSystem::withoutGlobalScope('tenant')->find($id);

        if (!$debugGradingSystem) {
            return $this->errorResponse('Grading system not found', null, 'NOT_FOUND', 404);
        }

        // Check tenant context
        $user = Auth::user();
        $currentTenantId = session('tenant_id') ?? $user->tenant_id ?? $user->current_tenant_id;

        if ($debugGradingSystem->tenant_id !== $currentTenantId) {
            return $this->errorResponse('Grading system not found in current tenant context', [
                'grading_system_tenant_id' => $debugGradingSystem->tenant_id,
                'current_tenant_id' => $currentTenantId,
                'debug_info' => [
                    'session_tenant_id' => session('tenant_id'),
                    'user_tenant_id' => $user->tenant_id ?? 'null',
                    'user_current_tenant_id' => $user->current_tenant_id ?? 'null'
                ]
            ], 'TENANT_MISMATCH', 404);
        }

        return $this->successResponse($debugGradingSystem->load('gradeScales.gradeLevels'));
    }

    /**
     * Update the specified grading system
     */
    public function update(UpdateGradingSystemRequest $request, $id): JsonResponse
    {
        try {
            $gradingSystem = GradingSystem::withoutGlobalScope('tenant')->findOrFail($id);
            $updatedGradingSystem = $this->gradingSystemService->updateGradingSystem($gradingSystem, $request->validated());

            return $this->successResponse($updatedGradingSystem, 'Grading system updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update grading system', null, 'UPDATE_ERROR', 422);
        }
    }

    /**
     * Remove the specified grading system
     */
    public function destroy($id): JsonResponse
    {
        try {
            $gradingSystem = GradingSystem::withoutGlobalScope('tenant')->findOrFail($id);

            // Check if it's the primary system before attempting deletion
            if ($gradingSystem->is_primary) {
                return $this->errorResponse(
                    'Cannot delete primary grading system. Please set another system as primary first, then try again.',
                    null,
                    'PRIMARY_SYSTEM_DELETE_ERROR',
                    422
                );
            }

            $this->gradingSystemService->deleteGradingSystem($gradingSystem);

            return $this->successResponse(null, 'Grading system deleted successfully');
        } catch (\Exception $e) {
            // Check if it's a specific error about primary system
            if (str_contains($e->getMessage(), 'Cannot delete primary grading system')) {
                return $this->errorResponse(
                    'Cannot delete primary grading system. Please set another system as primary first, then try again.',
                    null,
                    'PRIMARY_SYSTEM_DELETE_ERROR',
                    422
                );
            }

            return $this->errorResponse('Failed to delete grading system', null, 'DELETE_ERROR', 422);
        }
    }

    /**
     * Get primary grading system
     */
    public function primary(): JsonResponse
    {
        $primarySystem = $this->gradingSystemService->getPrimaryGradingSystem();

        if (!$primarySystem) {
            return $this->notFoundResponse('No primary grading system found');
        }

        return $this->successResponse($primarySystem->load('gradeScales.gradeLevels'));
    }

    /**
     * Set grading system as primary
     */
    public function setPrimary($id): JsonResponse
    {
        try {
            $gradingSystem = GradingSystem::withoutGlobalScope('tenant')->findOrFail($id);
            $this->gradingSystemService->setPrimaryGradingSystem($gradingSystem);

            return $this->successResponse($gradingSystem, 'Grading system set as primary successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to set primary grading system', null, 'SET_PRIMARY_ERROR', 422);
        }
    }
}
