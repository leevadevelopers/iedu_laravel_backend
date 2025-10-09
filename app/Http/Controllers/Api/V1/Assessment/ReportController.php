<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\Assessment\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseController
{
    public function __construct(
        protected ReportService $reportService
    ) {
        $this->middleware('auth:api');
    }

    /**
     * Get class grades summary
     */
    public function classGradesSummary(Request $request, int $classId, int $termId): JsonResponse
    {
        // if (!auth()->user()->can('assessment.reports.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $request->validate([
            'class_id' => 'nullable|exists:classes,id',
            'term_id' => 'nullable|exists:assessment_terms,id',
        ]);

        $summary = $this->reportService->getClassGradesSummary($classId, $termId);

        return $this->successResponse(
            $summary,
            'Class grades summary retrieved successfully'
        );
    }

    /**
     * Get student transcript
     */
    public function studentTranscript(Request $request, int $studentId, int $termId): JsonResponse
    {
        // if (!auth()->user()->can('assessment.reports.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Students can only view their own transcript
        if (auth()->user()->hasRole('student') && auth()->id() !== $studentId) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $transcript = $this->reportService->getStudentTranscript($studentId, $termId);

        return $this->successResponse(
            $transcript,
            'Student transcript retrieved successfully'
        );
    }
}

