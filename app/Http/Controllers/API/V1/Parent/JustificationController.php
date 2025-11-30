<?php

namespace App\Http\Controllers\API\V1\Parent;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Parent\JustifyAbsenceRequest;
use App\Http\Resources\Parent\AbsenceJustificationResource;
use App\Models\Academic\AbsenceJustification;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JustificationController extends BaseController
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Submit absence justification
     */
    public function justifyAbsence(JustifyAbsenceRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $justification = AbsenceJustification::create([
                'student_id' => $request->student_id,
                'date' => $request->date,
                'reason' => $request->reason,
                'description' => $request->description,
                'attachment_ids' => $request->attachments,
                'school_id' => $schoolId,
                'submitted_by' => auth('api')->id(),
                'status' => 'pending',
            ]);

            return $this->successResponse(
                new AbsenceJustificationResource($justification->load(['student', 'submitter'])),
                'Absence justification submitted successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to submit justification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get justifications for a student
     */
    public function getJustifications(Request $request, int $studentId): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Verify parent has access to this student
            $relationship = \App\Models\V1\SIS\Student\FamilyRelationship::where('guardian_user_id', $user->id)
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->first();

            if (!$relationship) {
                return $this->errorResponse('Access denied: You do not have access to this student', 403);
            }

            $justifications = AbsenceJustification::where('student_id', $studentId)
                ->with(['student', 'submitter', 'reviewer'])
                ->orderBy('date', 'desc')
                ->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(
                AbsenceJustificationResource::collection($justifications),
                'Justifications retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve justifications: ' . $e->getMessage(),
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

