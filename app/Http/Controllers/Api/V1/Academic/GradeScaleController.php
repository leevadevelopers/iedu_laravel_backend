<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradeScale;
use App\Http\Requests\Academic\StoreGradeScaleRequest;
use App\Http\Requests\Academic\UpdateGradeScaleRequest;
use App\Http\Resources\Academic\GradeScaleResource;
use App\Services\V1\Academic\GradeScaleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradeScaleController extends Controller
{
    protected GradeScaleService $gradeScaleService;

    public function __construct(GradeScaleService $gradeScaleService)
    {
        $this->gradeScaleService = $gradeScaleService;
    }

    /**
     * Display a listing of grade scales
     */
    public function index(Request $request): JsonResponse
    {
        $gradeScales = $this->gradeScaleService->getGradeScales($request->all());

        return response()->json([
            'status' => 'success',
            'data' => GradeScaleResource::collection($gradeScales),
            'meta' => [
                'total' => $gradeScales->total(),
                'per_page' => $gradeScales->perPage(),
                'current_page' => $gradeScales->currentPage(),
                'last_page' => $gradeScales->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created grade scale
     */
    public function store(StoreGradeScaleRequest $request): JsonResponse
    {
        try {
            $gradeScale = $this->gradeScaleService->createGradeScale($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale created successfully',
                'data' => new GradeScaleResource($gradeScale)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create grade scale',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified grade scale
     */
    public function show(GradeScale $gradeScale): JsonResponse
    {
        $this->authorize('view', $gradeScale);

        return response()->json([
            'status' => 'success',
            'data' => new GradeScaleResource($gradeScale->load(['gradeLevels', 'gradingSystem']))
        ]);
    }

    /**
     * Update the specified grade scale
     */
    public function update(UpdateGradeScaleRequest $request, GradeScale $gradeScale): JsonResponse
    {
        $this->authorize('update', $gradeScale);

        try {
            $updatedGradeScale = $this->gradeScaleService->updateGradeScale($gradeScale, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale updated successfully',
                'data' => new GradeScaleResource($updatedGradeScale)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update grade scale',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified grade scale
     */
    public function destroy(GradeScale $gradeScale): JsonResponse
    {
        $this->authorize('delete', $gradeScale);

        try {
            $this->gradeScaleService->deleteGradeScale($gradeScale);

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete grade scale',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Set grade scale as default
     */
    public function setDefault(GradeScale $gradeScale): JsonResponse
    {
        $this->authorize('update', $gradeScale);

        try {
            $this->gradeScaleService->setAsDefault($gradeScale);

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale set as default successfully',
                'data' => new GradeScaleResource($gradeScale)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set grade scale as default',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get grade scales by type
     */
    public function byType(string $type): JsonResponse
    {
        $gradeScales = $this->gradeScaleService->getGradeScalesByType($type);

        return response()->json([
            'status' => 'success',
            'data' => GradeScaleResource::collection($gradeScales)
        ]);
    }

    /**
     * Get default grade scale
     */
    public function default(): JsonResponse
    {
        $gradeScale = $this->gradeScaleService->getDefaultGradeScale();

        if (!$gradeScale) {
            return response()->json([
                'status' => 'error',
                'message' => 'No default grade scale found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new GradeScaleResource($gradeScale)
        ]);
    }

    /**
     * Get grade for percentage
     */
    public function getGradeForPercentage(GradeScale $gradeScale, Request $request): JsonResponse
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0|max:100'
        ]);

        $grade = $this->gradeScaleService->getGradeForPercentage($gradeScale, $request->percentage);

        return response()->json([
            'status' => 'success',
            'data' => $grade ? new \App\Http\Resources\Academic\GradeLevelResource($grade) : null
        ]);
    }
}
