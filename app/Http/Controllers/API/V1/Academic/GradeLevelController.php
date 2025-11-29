<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradeLevel;
use App\Http\Requests\Academic\StoreGradeLevelRequest;
use App\Http\Requests\Academic\UpdateGradeLevelRequest;
use App\Services\V1\Academic\GradeLevelService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GradeLevelController extends Controller
{
    use ApiResponseTrait;
    protected GradeLevelService $gradeLevelService;

    public function __construct(GradeLevelService $gradeLevelService)
    {
        $this->gradeLevelService = $gradeLevelService;
    }

    /**
     * Get the current school ID from authenticated user
     */
    protected function getCurrentSchoolId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try getCurrentSchool method first (preferred)
        if (method_exists($user, 'getCurrentSchool')) {
            $currentSchool = $user->getCurrentSchool();
            if ($currentSchool) {
                return $currentSchool->id;
            }
        }

        // Fallback to school_id attribute
        if (isset($user->school_id) && $user->school_id) {
            return $user->school_id;
        }

        // Try activeSchools relationship
        if (method_exists($user, 'activeSchools')) {
            $activeSchools = $user->activeSchools();
            if ($activeSchools && $activeSchools->count() > 0) {
                $firstSchool = $activeSchools->first();
                if ($firstSchool && isset($firstSchool->school_id)) {
                    return $firstSchool->school_id;
                }
            }
        }

        return null;
    }

    /**
     * Get the current tenant ID from authenticated user
     */
    protected function getCurrentTenantId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try tenant_id attribute first
        if (isset($user->tenant_id) && $user->tenant_id) {
            return $user->tenant_id;
        }

        // Try getCurrentTenant method
        if (method_exists($user, 'getCurrentTenant')) {
            $currentTenant = $user->getCurrentTenant();
            if ($currentTenant) {
                return $currentTenant->id;
            }
        }

        return null;
    }

    /**
     * Display a listing of grade levels
     */
    public function index(Request $request): JsonResponse
    {
        $gradeLevels = $this->gradeLevelService->getGradeLevels($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $gradeLevels->items(),
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
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id from user if not provided
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['tenant_id'] = $tenantId;

            $gradeLevel = $this->gradeLevelService->createGradeLevel($data);

            DB::commit();

            return $this->successResponse($gradeLevel, 'Grade level created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
    public function show(int $gradeLevelId): JsonResponse
    {
        try {
            $gradeLevel = $this->gradeLevelService->getGradeLevelById($gradeLevelId);

            if (!$gradeLevel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Grade level not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeLevel->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade level'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'data' => $gradeLevel->load(['gradeScale'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Grade level not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified grade level
     */
    public function update(\App\Http\Requests\Academic\UpdateGradeLevelRequest $request, int $id): JsonResponse
    {
        try {
            $gradeLevel = $this->gradeLevelService->getGradeLevelById($id);

            if (!$gradeLevel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Grade level not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeLevel->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade level'
                ], 403);
            }

            DB::beginTransaction();

            $updatedGradeLevel = $this->gradeLevelService->updateGradeLevel($gradeLevel, $request->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grade level updated successfully',
                'data' => $updatedGradeLevel
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
    public function destroy(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $gradeLevel = $this->gradeLevelService->getGradeLevelById($id);

            if (!$gradeLevel) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Grade level not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeLevel->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade level'
                ], 403);
            }

            $this->gradeLevelService->deleteGradeLevel($gradeLevel);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grade level deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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

        return $this->successResponse($gradeLevels);
    }

    /**
     * Get passing grade levels
     */
    public function passing(): JsonResponse
    {
        $gradeLevels = $this->gradeLevelService->getPassingGradeLevels();

        return $this->successResponse($gradeLevels);
    }

    /**
     * Get failing grade levels
     */
    public function failing(): JsonResponse
    {
        $gradeLevels = $this->gradeLevelService->getFailingGradeLevels();

        return $this->successResponse($gradeLevels);
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
            // Verify tenant access for all grade levels
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tenant ID is required'
                ], 422);
            }

            // Validate that all grade levels belong to the current tenant
            $gradeLevelIds = collect($request->grade_levels)->pluck('id');
            $gradeLevels = GradeLevel::whereIn('id', $gradeLevelIds)->get();

            foreach ($gradeLevels as $gradeLevel) {
                if ($gradeLevel->tenant_id != $tenantId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'One or more grade levels do not belong to your tenant'
                    ], 403);
                }
            }

            DB::beginTransaction();

            $this->gradeLevelService->reorderGradeLevels($request->grade_levels);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grade levels reordered successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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

        return $this->successResponse($gradeLevel);
    }
}
