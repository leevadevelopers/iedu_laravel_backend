<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradingSystem;
use App\Http\Requests\Academic\StoreGradingSystemRequest;
use App\Http\Requests\Academic\UpdateGradingSystemRequest;
use App\Http\Resources\Academic\GradingSystemResource;
use App\Services\V1\Academic\GradingSystemService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradingSystemController extends Controller
{
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

        return response()->json([
            'status' => 'success',
            'data' => GradingSystemResource::collection($gradingSystems)
        ]);
    }

    /**
     * Store a newly created grading system
     */
    public function store(StoreGradingSystemRequest $request): JsonResponse
    {
        try {
            $gradingSystem = $this->gradingSystemService->createGradingSystem($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system created successfully',
                'data' => new GradingSystemResource($gradingSystem)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified grading system
     */
    public function show(GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('view', $gradingSystem);

        return response()->json([
            'status' => 'success',
            'data' => new GradingSystemResource($gradingSystem->load('gradeScales.gradeLevels'))
        ]);
    }

    /**
     * Update the specified grading system
     */
    public function update(UpdateGradingSystemRequest $request, GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('update', $gradingSystem);

        try {
            $updatedGradingSystem = $this->gradingSystemService->updateGradingSystem($gradingSystem, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system updated successfully',
                'data' => new GradingSystemResource($updatedGradingSystem)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified grading system
     */
    public function destroy(GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('delete', $gradingSystem);

        try {
            $this->gradingSystemService->deleteGradingSystem($gradingSystem);

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get primary grading system
     */
    public function primary(): JsonResponse
    {
        $primarySystem = $this->gradingSystemService->getPrimaryGradingSystem();

        if (!$primarySystem) {
            return response()->json([
                'status' => 'error',
                'message' => 'No primary grading system found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new GradingSystemResource($primarySystem->load('gradeScales.gradeLevels'))
        ]);
    }

    /**
     * Set grading system as primary
     */
    public function setPrimary(GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('update', $gradingSystem);

        try {
            $this->gradingSystemService->setPrimaryGradingSystem($gradingSystem);

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system set as primary successfully',
                'data' => new GradingSystemResource($gradingSystem)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set primary grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
