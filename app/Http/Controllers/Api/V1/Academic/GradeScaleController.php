<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradeScale;
use App\Http\Requests\Academic\StoreGradeScaleRequest;
use App\Http\Requests\Academic\UpdateGradeScaleRequest;
use App\Services\V1\Academic\GradeScaleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GradeScaleController extends Controller
{
    use ApiResponseTrait;
    protected GradeScaleService $gradeScaleService;

    public function __construct(GradeScaleService $gradeScaleService)
    {
        $this->gradeScaleService = $gradeScaleService;
    }

    /**
     * Get current school ID from user's school_users relationship
     */
    protected function getCurrentSchoolId(): int
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $schoolUser = $user->activeSchools()->first();

        if (!$schoolUser) {
            throw new \Exception('User is not associated with any schools');
        }

        return $schoolUser->school_id;
    }

    /**
     * Display a listing of grade scales
     */
    public function index(Request $request): JsonResponse
    {
        $gradeScales = $this->gradeScaleService->getGradeScales($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $gradeScales->items(),
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

            return $this->successResponse($gradeScale, 'Grade scale created successfully', 201);
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
    public function show(int $gradeScaleId): JsonResponse
    {
        $gradeScale = GradeScale::where('id', $gradeScaleId)
            ->where('school_id', $this->getCurrentSchoolId())
            ->with(['gradeLevels', 'gradingSystem'])
            ->first();

        if (!$gradeScale) {
            return response()->json([
                'status' => 'error',
                'message' => 'Grade scale not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $gradeScale
        ]);
    }

    /**
     * Update the specified grade scale
     */
    public function update(UpdateGradeScaleRequest $request, int $gradeScaleId): JsonResponse
    {
        try {
            $gradeScale = GradeScale::where('id', $gradeScaleId)
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if (!$gradeScale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Grade scale not found'
                ], 404);
            }

            $updatedGradeScale = $this->gradeScaleService->updateGradeScale($gradeScale, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale updated successfully',
                'data' => $updatedGradeScale
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
    public function destroy(int $gradeScaleId): JsonResponse
    {
        try {
            $gradeScale = GradeScale::where('id', $gradeScaleId)
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if (!$gradeScale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Grade scale not found'
                ], 404);
            }

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
    public function setDefault(int $gradeScaleId): JsonResponse
    {
        try {
            $gradeScale = GradeScale::where('id', $gradeScaleId)
                ->where('school_id', $this->getCurrentSchoolId())
                ->first();

            if (!$gradeScale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Grade scale not found'
                ], 404);
            }

            $this->gradeScaleService->setAsDefault($gradeScale);

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale set as default successfully',
                'data' => $gradeScale
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
            'data' => $gradeScales
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

        return $this->successResponse($gradeScale);
    }

    /**
     * Get grade for percentage
     */
    public function getGradeForPercentage(int $gradeScaleId, Request $request): JsonResponse
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0|max:100'
        ]);

        $gradeScale = GradeScale::where('id', $gradeScaleId)
            ->where('school_id', $this->getCurrentSchoolId())
            ->first();

        if (!$gradeScale) {
            return response()->json([
                'status' => 'error',
                'message' => 'Grade scale not found'
            ], 404);
        }

        $grade = $this->gradeScaleService->getGradeForPercentage($gradeScale, $request->percentage);

        return $this->successResponse($grade);
    }
}
