<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreAssessmentTypeRequest;
use App\Http\Requests\Assessment\UpdateAssessmentTypeRequest;
use App\Http\Resources\Assessment\AssessmentTypeResource;
use App\Models\Assessment\AssessmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssessmentTypeController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * List all assessment types
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssessmentType::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Only active types
        if ($request->boolean('only_active')) {
            $query->active();
        }

        // With counts
        if ($request->boolean('with_counts')) {
            $query->withCount('assessments');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $types = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            AssessmentTypeResource::collection($types),
            'Assessment types retrieved successfully'
        );
    }

    /**
     * Create a new assessment type
     */
    public function store(StoreAssessmentTypeRequest $request): JsonResponse
    {
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id;

        $type = AssessmentType::create(array_merge(
            $request->validated(),
            ['tenant_id' => $tenantId]
        ));

        return $this->successResponse(
            new AssessmentTypeResource($type),
            'Assessment type created successfully',
            201
        );
    }

    /**
     * Show a specific assessment type
     */
    public function show(AssessmentType $assessmentType): JsonResponse
    {
        $assessmentType->loadCount('assessments');

        return $this->successResponse(
            new AssessmentTypeResource($assessmentType),
            'Assessment type retrieved successfully'
        );
    }

    /**
     * Update an assessment type
     */
    public function update(UpdateAssessmentTypeRequest $request, AssessmentType $assessmentType): JsonResponse
    {
        $assessmentType->update($request->validated());

        return $this->successResponse(
            new AssessmentTypeResource($assessmentType),
            'Assessment type updated successfully'
        );
    }

    /**
     * Delete an assessment type
     */
    public function destroy(AssessmentType $assessmentType): JsonResponse
    {
        // Check if type is in use
        if ($assessmentType->assessments()->exists()) {
            return $this->errorResponse(
                'Cannot delete assessment type that is in use',
                422
            );
        }

        $assessmentType->delete();

        return $this->successResponse(
            null,
            'Assessment type deleted successfully'
        );
    }

    /**
     * Get all active types
     */
    public function getActive(): JsonResponse
    {
        $types = AssessmentType::active()
            ->orderBy('name')
            ->get();

        return $this->successResponse(
            AssessmentTypeResource::collection($types),
            'Active assessment types retrieved successfully'
        );
    }

    /**
     * Activate an assessment type
     */
    public function activate(AssessmentType $assessmentType): JsonResponse
    {
        $assessmentType->update(['is_active' => true]);

        return $this->successResponse(
            new AssessmentTypeResource($assessmentType),
            'Assessment type activated successfully'
        );
    }

    /**
     * Deactivate an assessment type
     */
    public function deactivate(AssessmentType $assessmentType): JsonResponse
    {
        $assessmentType->update(['is_active' => false]);

        return $this->successResponse(
            new AssessmentTypeResource($assessmentType),
            'Assessment type deactivated successfully'
        );
    }
}

