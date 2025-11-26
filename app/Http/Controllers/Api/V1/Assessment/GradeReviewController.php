<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreGradeReviewRequest;
use App\Http\Requests\Assessment\UpdateGradeReviewRequest;
use App\Http\Resources\Assessment\GradeReviewResource;
use App\Models\Assessment\GradeReview;
use App\Services\Assessment\GradeReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeReviewController extends BaseController
{
    public function __construct(
        protected GradeReviewService $gradeReviewService
    ) {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-reviews.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $query = GradeReview::with(['gradeEntry', 'gradeEntry.student', 'requester', 'reviewer']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Students/parents see only their reviews
        if (auth()->user()->hasRole(['student', 'parent'])) {
            $query->where('requester_id', auth()->id());
        }

        // Teachers see reviews for their grade entries
        // Note: GradeEntry doesn't have an assessment relationship, it stores assessment info directly
        if (auth()->user()->hasRole('teacher')) {
            // You can add teacher filtering logic here if needed
            // For now, teachers see all reviews
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $reviews = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            GradeReviewResource::collection($reviews),
            'Grade reviews retrieved successfully'
        );
    }

    public function store(StoreGradeReviewRequest $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-reviews.create')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Check if user can request review
        $canRequest = $this->gradeReviewService->canRequestReview($request->grade_entry_id, auth()->id());
        if (!$canRequest['allowed']) {
            return $this->errorResponse($canRequest['reason'] ?? 'Cannot request review for this grade', 422);
        }

        $gradeReview = $this->gradeReviewService->createReviewRequest($request->validated());

        return $this->successResponse(
            new GradeReviewResource($gradeReview),
            'Grade review request created successfully',
            201
        );
    }

    public function show(GradeReview $gradeReview): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-reviews.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Students/parents can only view their own reviews
        if (auth()->user()->hasRole(['student', 'parent']) && $gradeReview->requester_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $gradeReview->load(['gradeEntry', 'gradeEntry.student', 'requester', 'reviewer']);

        return $this->successResponse(
            new GradeReviewResource($gradeReview),
            'Grade review retrieved successfully'
        );
    }

    public function update(UpdateGradeReviewRequest $request, GradeReview $gradeReview): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-reviews.resolve')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradeReview = $this->gradeReviewService->updateReviewStatus($gradeReview, $request->validated());

        return $this->successResponse(
            new GradeReviewResource($gradeReview),
            'Grade review updated successfully'
        );
    }

    public function destroy(GradeReview $gradeReview): JsonResponse
    {
            // if (!auth()->user()->can('assessment.grade-reviews.manage')) {
            //     return $this->errorResponse('Unauthorized', 403);
            // }

        $gradeReview->delete();

        return $this->successResponse(
            null,
            'Grade review deleted successfully'
        );
    }
}

