<?php

namespace App\Http\Controllers\API\V1\Reception;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Reception\StoreVisitorRequest;
use App\Http\Resources\Reception\VisitorResource;
use App\Models\Reception\Visitor;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceptionController extends BaseController
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Log a visitor
     */
    public function logVisitor(StoreVisitorRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $visitor = Visitor::create([
                'name' => $request->name,
                'type' => $request->type,
                'student_id' => $request->student_id,
                'purpose' => $request->purpose,
                'resolved' => $request->get('resolved', false),
                'notes' => $request->notes,
                'school_id' => $schoolId,
                'attended_by' => auth('api')->id(),
                'arrived_at' => now(),
            ]);

            return $this->successResponse(
                new VisitorResource($visitor->load(['student', 'attendedBy'])),
                'Visitor logged successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to log visitor: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * List visitors
     */
    public function listVisitors(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $query = Visitor::query();

            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            if ($request->filled('date')) {
                $query->whereDate('created_at', $request->date);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('resolved')) {
                $query->where('resolved', $request->boolean('resolved'));
            }

            if ($request->filled('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            $visitors = $query->with(['student', 'attendedBy'])
                ->latest()
                ->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(
                VisitorResource::collection($visitors),
                'Visitors retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve visitors: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Mark visitor as resolved
     */
    public function markResolved(Visitor $visitor): JsonResponse
    {
        try {
            $visitor->markAsResolved();

            return $this->successResponse(
                new VisitorResource($visitor->fresh()->load(['student', 'attendedBy'])),
                'Visitor marked as resolved'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to mark visitor as resolved: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get current school ID helper
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }
}

