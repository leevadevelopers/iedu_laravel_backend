<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreAssessmentSettingsRequest;
use App\Http\Requests\Assessment\UpdateAssessmentSettingsRequest;
use App\Http\Resources\Assessment\AssessmentSettingsResource;
use App\Models\Assessment\AssessmentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentSettingsController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

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
    }

    public function store(StoreAssessmentSettingsRequest $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $tenantId = session('tenant_id') ?? auth()->user()->tenant_id;

        $settings = AssessmentSettings::create(array_merge(
            $request->validated(),
            ['tenant_id' => $tenantId]
        ));

        return $this->successResponse(
            new AssessmentSettingsResource($settings),
            'Assessment settings created successfully',
            201
        );
    }

    public function show(AssessmentSettings $assessmentSetting): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $assessmentSetting->load('academicTerm');

        return $this->successResponse(
            new AssessmentSettingsResource($assessmentSetting),
            'Assessment settings retrieved successfully'
        );
    }

    public function update(UpdateAssessmentSettingsRequest $request, AssessmentSettings $assessmentSetting): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $assessmentSetting->update($request->validated());

        return $this->successResponse(
            new AssessmentSettingsResource($assessmentSetting),
            'Assessment settings updated successfully'
        );
    }

    public function destroy(AssessmentSettings $assessmentSetting): JsonResponse
    {
        // if (!auth()->user()->can('assessment.settings.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $assessmentSetting->delete();

        return $this->successResponse(
            null,
            'Assessment settings deleted successfully'
        );
    }

    public function getByTerm(int $termId): JsonResponse
    {
        $settings = AssessmentSettings::where('academic_term_id', $termId)
            ->where('tenant_id', session('tenant_id') ?? auth()->user()->tenant_id)
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

