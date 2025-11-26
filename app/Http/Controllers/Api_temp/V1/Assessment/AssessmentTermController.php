<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreAssessmentTermRequest;
use App\Http\Requests\Assessment\UpdateAssessmentTermRequest;
use App\Http\Resources\Assessment\AssessmentTermResource;
use App\Models\Assessment\AssessmentTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssessmentTermController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * List all assessment terms
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssessmentTerm::with(['academicTerm', 'creator']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by academic term
        if ($request->filled('academic_term_id')) {
            $query->where('academic_term_id', $request->academic_term_id);
        }

        // Filter by published status
        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // Only active terms
        if ($request->boolean('only_active')) {
            $query->active();
        }

        // Only published terms
        if ($request->boolean('only_published')) {
            $query->published();
        }

        // With counts
        if ($request->boolean('with_counts')) {
            $query->withCount(['assessments', 'gradebooks']);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $terms = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            AssessmentTermResource::collection($terms),
            'Assessment terms retrieved successfully'
        );
    }

    /**
     * Create a new assessment term
     */
    public function store(StoreAssessmentTermRequest $request): JsonResponse
    {
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id;

        $term = AssessmentTerm::create(array_merge(
            $request->validated(),
            [
                'tenant_id' => $tenantId,
                'created_by' => Auth::id(),
            ]
        ));

        $term->load(['academicTerm', 'creator']);

        return $this->successResponse(
            new AssessmentTermResource($term),
            'Assessment term created successfully',
            201
        );
    }

    /**
     * Show a specific assessment term
     */
    public function show(AssessmentTerm $assessmentTerm): JsonResponse
    {
        $assessmentTerm->load([
            'academicTerm',
            'creator',
            'assessments.type',
            'assessments.subject',
            'assessments.class',
            'gradebooks',
            'settings',
        ]);

        $assessmentTerm->loadCount(['assessments', 'gradebooks']);

        return $this->successResponse(
            new AssessmentTermResource($assessmentTerm),
            'Assessment term retrieved successfully'
        );
    }

    /**
     * Update an assessment term
     */
    public function update(UpdateAssessmentTermRequest $request, AssessmentTerm $assessmentTerm): JsonResponse
    {
        $assessmentTerm->update($request->validated());

        $assessmentTerm->load(['academicTerm', 'creator']);

        return $this->successResponse(
            new AssessmentTermResource($assessmentTerm),
            'Assessment term updated successfully'
        );
    }

    /**
     * Delete an assessment term
     */
    public function destroy(AssessmentTerm $assessmentTerm): JsonResponse
    {
        // Check if term has assessments
        if ($assessmentTerm->assessments()->exists()) {
            return $this->errorResponse(
                'Cannot delete term with existing assessments',
                422
            );
        }

        $assessmentTerm->delete();

        return $this->successResponse(
            null,
            'Assessment term deleted successfully'
        );
    }

    /**
     * Publish an assessment term
     */
    public function publish(AssessmentTerm $assessmentTerm): JsonResponse
    {
        $assessmentTerm->update([
            'is_published' => true,
        ]);

        return $this->successResponse(
            new AssessmentTermResource($assessmentTerm),
            'Assessment term published successfully'
        );
    }

    /**
     * Unpublish an assessment term
     */
    public function unpublish(AssessmentTerm $assessmentTerm): JsonResponse
    {
        $assessmentTerm->update([
            'is_published' => false,
        ]);

        return $this->successResponse(
            new AssessmentTermResource($assessmentTerm),
            'Assessment term unpublished successfully'
        );
    }

    /**
     * Activate an assessment term
     */
    public function activate(AssessmentTerm $assessmentTerm): JsonResponse
    {
        $assessmentTerm->update([
            'is_active' => true,
        ]);

        return $this->successResponse(
            new AssessmentTermResource($assessmentTerm),
            'Assessment term activated successfully'
        );
    }

    /**
     * Deactivate an assessment term
     */
    public function deactivate(AssessmentTerm $assessmentTerm): JsonResponse
    {
        $assessmentTerm->update([
            'is_active' => false,
        ]);

        return $this->successResponse(
            new AssessmentTermResource($assessmentTerm),
            'Assessment term deactivated successfully'
        );
    }

    /**
     * Get current active term
     */
    public function getCurrent(Request $request): JsonResponse
    {
        $currentDate = now();
        
        $term = AssessmentTerm::where('is_active', true)
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->with(['academicTerm', 'creator'])
            ->first();

        if (!$term) {
            return $this->errorResponse('No active term found for current date', 404);
        }

        return $this->successResponse(
            new AssessmentTermResource($term),
            'Current assessment term retrieved successfully'
        );
    }

    /**
     * Get statistics for a term
     */
    public function statistics(AssessmentTerm $assessmentTerm): JsonResponse
    {
        $stats = [
            'total_assessments' => $assessmentTerm->assessments()->count(),
            'assessments_by_status' => $assessmentTerm->assessments()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'assessments_by_type' => $assessmentTerm->assessments()
                ->with('type')
                ->get()
                ->groupBy('type.name')
                ->map->count(),
            'total_gradebooks' => $assessmentTerm->gradebooks()->count(),
            'published_assessments' => $assessmentTerm->assessments()
                ->whereNotNull('published_at')
                ->count(),
            'locked_assessments' => $assessmentTerm->assessments()
                ->where('is_locked', true)
                ->count(),
        ];

        return $this->successResponse(
            $stats,
            'Term statistics retrieved successfully'
        );
    }

    /**
     * Clone an assessment term
     */
    public function clone(Request $request, AssessmentTerm $assessmentTerm): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'clone_assessments' => 'nullable|boolean',
        ]);

        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id;

        $newTerm = AssessmentTerm::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'academic_term_id' => $assessmentTerm->academic_term_id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_published' => false,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        // Clone assessments if requested
        if ($validated['clone_assessments'] ?? false) {
            foreach ($assessmentTerm->assessments as $assessment) {
                $newAssessment = $assessment->replicate();
                $newAssessment->term_id = $newTerm->id;
                $newAssessment->status = 'draft';
                $newAssessment->is_locked = false;
                $newAssessment->published_at = null;
                $newAssessment->published_by = null;
                $newAssessment->save();

                // Clone components
                foreach ($assessment->components as $component) {
                    $newComponent = $component->replicate();
                    $newComponent->assessment_id = $newAssessment->id;
                    $newComponent->save();
                }
            }
        }

        $newTerm->load(['academicTerm', 'creator']);
        $newTerm->loadCount('assessments');

        return $this->successResponse(
            new AssessmentTermResource($newTerm),
            'Assessment term cloned successfully',
            201
        );
    }
}

