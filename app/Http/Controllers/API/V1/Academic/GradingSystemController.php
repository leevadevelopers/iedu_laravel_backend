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
use Illuminate\Support\Facades\DB;

class GradingSystemController extends Controller
{
    use ApiResponseTrait;
    protected GradingSystemService $gradingSystemService;

    public function __construct(GradingSystemService $gradingSystemService)
    {
        $this->gradingSystemService = $gradingSystemService;
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
            $data['school_id'] = $this->getCurrentSchoolId();

            $gradingSystem = $this->gradingSystemService->createGradingSystem($data);

            DB::commit();

            return $this->successResponse($gradingSystem, 'Grading system created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create grading system', null, 'CREATE_ERROR', 422);
        }
    }

    /**
     * Display the specified grading system
     */
    public function show($id): JsonResponse
    {
        try {
            // Find grading system - TenantScope will automatically filter by tenant
            $gradingSystem = GradingSystem::find($id);

            if (!$gradingSystem) {
                return $this->errorResponse('Grading system not found', null, 'NOT_FOUND', 404);
            }

            // Verify tenant access explicitly
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradingSystem->tenant_id != $tenantId) {
                return $this->errorResponse('You do not have access to this grading system', null, 'ACCESS_DENIED', 403);
            }

            return $this->successResponse($gradingSystem->load('gradeScales.gradeLevels'));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve grading system', null, 'RETRIEVE_ERROR', 500);
        }
    }

    /**
     * Update the specified grading system
     */
    public function update(UpdateGradingSystemRequest $request, $id): JsonResponse
    {
        try {
            // Find grading system - TenantScope will automatically filter by tenant
            $gradingSystem = GradingSystem::find($id);

            if (!$gradingSystem) {
                return $this->errorResponse('Grading system not found', null, 'NOT_FOUND', 404);
            }

            // Verify tenant access explicitly
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradingSystem->tenant_id != $tenantId) {
                return $this->errorResponse('You do not have access to this grading system', null, 'ACCESS_DENIED', 403);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $data['tenant_id'] = $tenantId;
            $data['school_id'] = $this->getCurrentSchoolId();
            $updatedGradingSystem = $this->gradingSystemService->updateGradingSystem($gradingSystem, $data);

            DB::commit();

            return $this->successResponse($updatedGradingSystem, 'Grading system updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update grading system', null, 'UPDATE_ERROR', 422);
        }
    }

    /**
     * Remove the specified grading system
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Find grading system - TenantScope will automatically filter by tenant
            $gradingSystem = GradingSystem::find($id);

            if (!$gradingSystem) {
                DB::rollBack();
                return $this->errorResponse('Grading system not found', null, 'NOT_FOUND', 404);
            }

            // Verify tenant access explicitly
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradingSystem->tenant_id != $tenantId) {
                DB::rollBack();
                return $this->errorResponse('You do not have access to this grading system', null, 'ACCESS_DENIED', 403);
            }

            // Check if it's the primary system before attempting deletion
            if ($gradingSystem->is_primary) {
                DB::rollBack();
                return $this->errorResponse(
                    'Cannot delete primary grading system. Please set another system as primary first, then try again.',
                    null,
                    'PRIMARY_SYSTEM_DELETE_ERROR',
                    422
                );
            }

            $this->gradingSystemService->deleteGradingSystem($gradingSystem);

            DB::commit();

            return $this->successResponse(null, 'Grading system deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
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
            DB::beginTransaction();

            // Find grading system - TenantScope will automatically filter by tenant
            $gradingSystem = GradingSystem::find($id);

            if (!$gradingSystem) {
                DB::rollBack();
                return $this->errorResponse('Grading system not found', null, 'NOT_FOUND', 404);
            }

            // Verify tenant access explicitly
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradingSystem->tenant_id != $tenantId) {
                DB::rollBack();
                return $this->errorResponse('You do not have access to this grading system', null, 'ACCESS_DENIED', 403);
            }

            $this->gradingSystemService->setPrimaryGradingSystem($gradingSystem);

            DB::commit();

            return $this->successResponse($gradingSystem, 'Grading system set as primary successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to set primary grading system', null, 'SET_PRIMARY_ERROR', 422);
        }
    }
}
