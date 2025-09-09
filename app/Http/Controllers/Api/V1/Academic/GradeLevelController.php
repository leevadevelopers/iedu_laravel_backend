<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradeLevel;
use App\Http\Requests\Academic\StoreGradeLevelRequest;
use App\Http\Requests\Academic\UpdateGradeLevelRequest;
use App\Http\Resources\Academic\GradeLevelResource;
use App\Services\V1\Academic\GradeLevelService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradeLevelController extends Controller
{
    protected GradeLevelService $gradeLevelService;

    public function __construct(GradeLevelService $gradeLevelService)
    {
        $this->gradeLevelService = $gradeLevelService;
    }

    /**
     * Display a listing of grade levels
     */
    public function index(Request $request): JsonResponse
    {
        $gradeLevels = $this->gradeLevelService->getGradeLevels($request->all());

        return response()->json([
            'status' => 'success',
            'data' => GradeLevelResource::collection($gradeLevels),
            'meta' => [
                'total' => $gradeLevels->total(),
                'per_page' => $gradeLevels->perPage(),
                'current_page' => $gradeLevels->currentPage(),
                'last_page' => $gradeLevels->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created grade level
     */
    public function store(StoreGradeLevelRequest $request): JsonResponse
    {
        try {
            $gradeLevel = $this->gradeLevelService->createGradeLevel($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grade level created successfully',
                'data' => new GradeLevelResource($gradeLevel)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create grade level',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified grade level
     */
    public function show(GradeLevel $gradeLevel): JsonResponse
    {
        $this->authorize('view', $gradeLevel);

        return response()->json([
            'status' => 'success',
            'data' => new GradeLevelResource($gradeLevel->load(['gradeScale']))
        ]);
    }

    /**
     * Update the specified grade level
     */
    public function update(UpdateGradeLevelRequest $request, GradeLevel $gradeLevel): JsonResponse
    {
        $this->authorize('update', $gradeLevel);

        try {
            $updatedGradeLevel = $this->gradeLevelService->updateGradeLevel($gradeLevel, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grade level updated successfully',
                'data' => new GradeLevelResource($updatedGradeLevel)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update grade level',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified grade level
     */
    public function destroy(GradeLevel $gradeLevel): JsonResponse
    {
        $this->authorize('delete', $gradeLevel);

        try {
            $this->gradeLevelService->deleteGradeLevel($gradeLevel);

            return response()->json([
                'status' => 'success',
                'message' => 'Grade level deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete grade level',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get grade levels by grade scale
     */
    public function byGradeScale(int $gradeScaleId): JsonResponse
    {
        $gradeLevels = $this->gradeLevelService->getGradeLevelsByGradeScale($gradeScaleId);

        return response()->json([
            'status' => 'success',
            'data' => GradeLevelResource::collection($gradeLevels)
        ]);
    }

    /**
     * Get passing grade levels
     */
    public function passing(): JsonResponse
    {
        $gradeLevels = $this->gradeLevelService->getPassingGradeLevels();

        return response()->json([
            'status' => 'success',
            'data' => GradeLevelResource::collection($gradeLevels)
        ]);
    }

    /**
     * Get failing grade levels
     */
    public function failing(): JsonResponse
    {
        $gradeLevels = $this->gradeLevelService->getFailingGradeLevels();

        return response()->json([
            'status' => 'success',
            'data' => GradeLevelResource::collection($gradeLevels)
        ]);
    }

    /**
     * Reorder grade levels
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'grade_levels' => 'required|array',
            'grade_levels.*.id' => 'required|exists:grade_levels,id',
            'grade_levels.*.sort_order' => 'required|integer|min:0'
        ]);

        try {
            $this->gradeLevelService->reorderGradeLevels($request->grade_levels);

            return response()->json([
                'status' => 'success',
                'message' => 'Grade levels reordered successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reorder grade levels',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get grade level for percentage
     */
    public function getGradeForPercentage(Request $request): JsonResponse
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0|max:100',
            'grade_scale_id' => 'required|exists:grade_scales,id'
        ]);

        $gradeLevel = $this->gradeLevelService->getGradeForPercentage(
            $request->grade_scale_id,
            $request->percentage
        );

        return response()->json([
            'status' => 'success',
            'data' => $gradeLevel ? new GradeLevelResource($gradeLevel) : null
        ]);
    }
}
