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
use Illuminate\Support\Facades\DB;

class GradeScaleController extends Controller
{
    use ApiResponseTrait;
    protected GradeScaleService $gradeScaleService;

    public function __construct(GradeScaleService $gradeScaleService)
    {
        $this->gradeScaleService = $gradeScaleService;
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

            $gradeScale = $this->gradeScaleService->createGradeScale($data);

            DB::commit();

            return $this->successResponse($gradeScale, 'Grade scale created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId || $gradeScale->tenant_id != $tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this grade scale'
            ], 403);
        }

        $gradeScale->load(['gradeLevels', 'gradingSystem']);

        return response()->json([
            'status' => 'success',
            'data' => $gradeScale
        ]);
    }

    /**
     * Update the specified grade scale
     */
    public function update(UpdateGradeScaleRequest $request, GradeScale $gradeScale): JsonResponse
    {
        try {
            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeScale->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade scale'
                ], 403);
            }

            DB::beginTransaction();

            $updatedGradeScale = $this->gradeScaleService->updateGradeScale($gradeScale, $request->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale updated successfully',
                'data' => $updatedGradeScale
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
        try {
            DB::beginTransaction();

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeScale->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade scale'
                ], 403);
            }

            $this->gradeScaleService->deleteGradeScale($gradeScale);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
        try {
            DB::beginTransaction();

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeScale->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade scale'
                ], 403);
            }

            $this->gradeScaleService->setAsDefault($gradeScale);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grade scale set as default successfully',
                'data' => $gradeScale
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
    public function getGradeForPercentage(GradeScale $gradeScale, Request $request): JsonResponse
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0|max:100'
        ]);

        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId || $gradeScale->tenant_id != $tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this grade scale'
            ], 403);
        }

        $grade = $this->gradeScaleService->getGradeForPercentage($gradeScale, $request->percentage);

        return $this->successResponse($grade);
    }
}
