<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreAssessmentSettingsRequest;
use App\Http\Requests\Assessment\UpdateAssessmentSettingsRequest;
use App\Http\Resources\Assessment\AssessmentSettingsResource;
use App\Models\Assessment\AssessmentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentSettingsController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
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

    public function index(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        try {
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId) {
                return $this->errorResponse('Tenant ID is required', 422);
            }

            // Build query - TenantScope from BaseModel should handle tenant filtering automatically
            $query = AssessmentSettings::with('academicTerm');

            // Filter by academic term
            if ($request->filled('academic_term_id')) {
                $query->where('academic_term_id', $request->academic_term_id);
            }

            $settings = $query->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(
                AssessmentSettingsResource::collection($settings),
                'Assessment settings retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve assessment settings: ' . $e->getMessage(), 500);
        }
    }

    public function store(StoreAssessmentSettingsRequest $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return $this->errorResponse('Tenant ID is required', 422);
        }

        try {
            DB::beginTransaction();

            $settings = AssessmentSettings::create(array_merge(
                $request->validated(),
                ['tenant_id' => $tenantId]
            ));

            DB::commit();

            return $this->successResponse(
                new AssessmentSettingsResource($settings),
                'Assessment settings created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create assessment settings: ' . $e->getMessage(), 422);
        }
    }

    public function show($id): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return $this->errorResponse('Tenant ID is required', 422);
        }

        // Find assessment setting with tenant check
        $assessmentSetting = AssessmentSettings::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$assessmentSetting) {
            return $this->errorResponse('Assessment settings not found', 404);
        }

        $assessmentSetting->load('academicTerm');

        return $this->successResponse(
            new AssessmentSettingsResource($assessmentSetting),
            'Assessment settings retrieved successfully'
        );
    }

    public function update(UpdateAssessmentSettingsRequest $request, $id): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return $this->errorResponse('Tenant ID is required', 422);
        }

        try {
            DB::beginTransaction();

            // Find assessment setting with tenant check
            $assessmentSetting = AssessmentSettings::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$assessmentSetting) {
                DB::rollBack();
                return $this->errorResponse('Assessment settings not found', 404);
            }

            $assessmentSetting->update($request->validated());

            DB::commit();

            return $this->successResponse(
                new AssessmentSettingsResource($assessmentSetting),
                'Assessment settings updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update assessment settings: ' . $e->getMessage(), 422);
        }
    }

    public function destroy($id): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return $this->errorResponse('Tenant ID is required', 422);
        }

        try {
            DB::beginTransaction();

            // Find assessment setting with tenant check
            $assessmentSetting = AssessmentSettings::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$assessmentSetting) {
                DB::rollBack();
                return $this->errorResponse('Assessment settings not found', 404);
            }

            $assessmentSetting->delete();

            DB::commit();

            return $this->successResponse(
                null,
                'Assessment settings deleted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to delete assessment settings: ' . $e->getMessage(), 422);
        }
    }

    public function getByTerm(int $termId): JsonResponse
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return $this->errorResponse('Tenant ID is required', 422);
        }

        $settings = AssessmentSettings::where('academic_term_id', $termId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$settings) {
            return $this->errorResponse('No settings found for this term', 404);
        }

        return $this->successResponse(
            new AssessmentSettingsResource($settings),
            'Assessment settings retrieved successfully'
        );
    }
}

