<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreAssessmentRequest;
use App\Http\Requests\Assessment\UpdateAssessmentRequest;
use App\Http\Resources\Assessment\AssessmentResource;
use App\Models\Assessment\Assessment;
use App\Services\Assessment\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends BaseController
{
    public function __construct(
        protected AssessmentService $assessmentService
    ) {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // if (!auth()->user()->hasPermissionTo('assessment.assessments.view')) {
        //     return $this->errorResponse('Unauthorized sss', 403);
        // }

        $query = Assessment::with(['type', 'term', 'subject', 'class', 'teacher', 'components']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by term
        if ($request->filled('term_id')) {
            $query->where('term_id', $request->term_id);
        }

        // Filter by subject
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by class
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by teacher
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->filled('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        // Only show teacher's own assessments if role is teacher
        if (auth()->user()->hasRole('teacher')) {
            $query->where('teacher_id', auth()->id());
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $assessments = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            AssessmentResource::collection($assessments),
            'Assessments retrieved successfully'
        );
    }

    public function store(StoreAssessmentRequest $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.assessments.create')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $assessment = $this->assessmentService->createAssessment($request->validated());

        return $this->successResponse(
            new AssessmentResource($assessment),
            'Assessment created successfully',
            201
        );
    }

    public function show(Assessment $assessment): JsonResponse
    {
        // if (!auth()->user()->can('assessment.assessments.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Teachers can only view their own assessments
        if (auth()->user()->hasRole('teacher') && $assessment->teacher_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $assessment->load(['type', 'term', 'subject', 'class', 'teacher', 'components', 'resources']);

        return $this->successResponse(
            new AssessmentResource($assessment),
            'Assessment retrieved successfully'
        );
    }

    public function update(UpdateAssessmentRequest $request, Assessment $assessment): JsonResponse
    {
        // if (!auth()->user()->can('assessment.assessments.edit')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Teachers can only update their own assessments
        if (auth()->user()->hasRole('teacher') && $assessment->teacher_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $assessment = $this->assessmentService->updateAssessment($assessment, $request->validated());

        return $this->successResponse(
            new AssessmentResource($assessment),
            'Assessment updated successfully'
        );
    }

    public function destroy(Assessment $assessment): JsonResponse
    {
        // if (!auth()->user()->can('assessment.assessments.delete')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Teachers can only delete their own assessments
        if (auth()->user()->hasRole('teacher') && $assessment->teacher_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $this->assessmentService->deleteAssessment($assessment);

        return $this->successResponse(
            null,
            'Assessment deleted successfully'
        );
    }

    public function updateStatus(Request $request, Assessment $assessment): JsonResponse
    {
        // if (!auth()->user()->can('assessment.assessments.edit')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Teachers can only update status of their own assessments
        if (auth()->user()->hasRole('teacher') && $assessment->teacher_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $request->validate([
            'status' => 'required|in:draft,scheduled,in_progress,completed,cancelled',
        ]);

        $assessment = $this->assessmentService->changeStatus($assessment, $request->status);

        return $this->successResponse(
            new AssessmentResource($assessment),
            'Assessment status updated successfully'
        );
    }

    public function lock(Assessment $assessment): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.publish')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $assessment = $this->assessmentService->lockAssessment($assessment);

        return $this->successResponse(
            new AssessmentResource($assessment),
            'Assessment locked successfully'
        );
    }
}

