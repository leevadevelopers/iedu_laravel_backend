<?php

namespace App\Http\Controllers\API\V1\Schedule;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\V1\Schedule\StudentsAttendanceHistoryService;
use App\Services\SchoolContextService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class StudentsAttendanceHistoryController extends BaseController
{
    protected StudentsAttendanceHistoryService $attendanceHistoryService;
    protected SchoolContextService $schoolContextService;

    public function __construct(
        StudentsAttendanceHistoryService $attendanceHistoryService,
        SchoolContextService $schoolContextService
    ) {
        $this->attendanceHistoryService = $attendanceHistoryService;
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Get the current school ID from authenticated user
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            $user = auth('api')->user();
            if ($user && isset($user->school_id)) {
                return $user->school_id;
            }
            return null;
        }
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

        if (isset($user->tenant_id) && $user->tenant_id) {
            return $user->tenant_id;
        }

        if (method_exists($user, 'getCurrentTenant')) {
            $currentTenant = $user->getCurrentTenant();
            if ($currentTenant) {
                return $currentTenant->id;
            }
        }

        $tenantId = session('tenant_id');
        if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
            $tenantId = (int) request()->header('X-Tenant-ID');
        }

        return $tenantId;
    }

    /**
     * Get list of students with aggregated attendance statistics
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'class_id' => 'nullable|exists:classes,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'period_type' => 'nullable|in:day,week,month,quarter,semester,year',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $schoolId = $this->getCurrentSchoolId();
            $tenantId = $this->getCurrentTenantId();

            if (!$schoolId) {
                return $this->errorResponse('School ID is required', 403);
            }

            $filters = [
                'school_id' => $schoolId,
                'tenant_id' => $tenantId,
                'class_id' => $request->class_id,
                'date_from' => $request->date_from ?? Carbon::now()->startOfYear()->toDateString(),
                'date_to' => $request->date_to ?? Carbon::now()->toDateString(),
            ];

            $results = $this->attendanceHistoryService->getStudentsHistory($filters);

            // Pagination (simple client-side pagination for now)
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            $total = count($results);
            $offset = ($page - 1) * $perPage;
            $paginatedResults = array_slice($results, $offset, $perPage);

            return $this->successResponse([
                'data' => $paginatedResults,
                'meta' => [
                    'current_page' => (int) $page,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ],
            ], 'Students attendance history retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve students attendance history: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get detailed information about a specific student's attendance
     */
    public function show(int $studentId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $schoolId = $this->getCurrentSchoolId();
            $tenantId = $this->getCurrentTenantId();

            if (!$schoolId) {
                return $this->errorResponse('School ID is required', 403);
            }

            $filters = [
                'school_id' => $schoolId,
                'tenant_id' => $tenantId,
                'date_from' => $request->date_from ?? Carbon::now()->startOfYear()->toDateString(),
                'date_to' => $request->date_to ?? Carbon::now()->toDateString(),
            ];

            $detail = $this->attendanceHistoryService->getStudentDetail($studentId, $filters);

            return $this->successResponse($detail, 'Student attendance detail retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve student attendance detail: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get statistics by period type
     */
    public function stats(int $studentId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period_type' => 'required|in:day,week,month,quarter,semester,year',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $start = Carbon::parse($request->date_from);
            $end = Carbon::parse($request->date_to);

            $stats = $this->attendanceHistoryService->calculatePeriodStats(
                $studentId,
                $request->period_type,
                $start,
                $end
            );

            // Get trend analysis
            $trend = $this->attendanceHistoryService->getTrendAnalysis($studentId, $start, $end);

            return $this->successResponse([
                'period_type' => $request->period_type,
                'period' => [
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                ],
                'statistics' => $stats,
                'trend' => $trend,
            ], 'Period statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve period statistics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get calendar data for attendance visualization
     */
    public function calendar(int $studentId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $start = Carbon::parse($request->date_from);
            $end = Carbon::parse($request->date_to);

            $calendarData = $this->attendanceHistoryService->getCalendarData($studentId, $start, $end);

            return $this->successResponse([
                'period' => [
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                ],
                'calendar' => $calendarData,
            ], 'Calendar data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve calendar data: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Export student attendance report (PDF/Excel)
     */
    public function export(int $studentId, Request $request, string $format): JsonResponse
    {
        try {
            // For now, return a message indicating export functionality
            // In production, implement actual PDF/Excel export using libraries like DomPDF, Laravel Excel, etc.
            
            $filters = $request->all();
            
            return $this->successResponse([
                'message' => 'Export functionality will be implemented',
                'format' => $format,
                'student_id' => $studentId,
                'filters' => $filters
            ], 'Export request received');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to export student attendance report: ' . $e->getMessage(),
                500
            );
        }
    }
}

