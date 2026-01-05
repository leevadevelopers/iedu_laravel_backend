<?php

namespace App\Http\Controllers\API\V1\Schedule;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\V1\Schedule\LessonSession;
use App\Models\V1\Schedule\LessonAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LessonsHistoryController extends BaseController
{
    /**
     * Get the current school ID from authenticated user
     */
    protected function getCurrentSchoolId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        if (method_exists($user, 'getCurrentSchool')) {
            $currentSchool = $user->getCurrentSchool();
            if ($currentSchool) {
                return $currentSchool->id;
            }
        }

        if (isset($user->school_id) && $user->school_id) {
            return $user->school_id;
        }

        if (method_exists($user, 'activeSchools')) {
            $activeSchools = $user->activeSchools();
            if ($activeSchools && $activeSchools->count() > 0) {
                $firstSchool = $activeSchools->first();
                if ($firstSchool && isset($firstSchool->school_id)) {
                    return $firstSchool->school_id;
                }
            }
        }

        return null;
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
     * Get historical lessons with advanced filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();
            $tenantId = $this->getCurrentTenantId();

            if (!$schoolId) {
                return $this->errorResponse('School ID is required', 403);
            }

            $query = LessonSession::with([
                'teacher',
                'subject',
                'class',
                'schedule'
            ])
            ->where('school_id', $schoolId);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            // Filter by teacher
            if ($request->has('teacher_id') && $request->teacher_id) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Filter by subject
            if ($request->has('subject_id') && $request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            // Filter by class
            if ($request->has('class_id') && $request->class_id) {
                $query->where('class_id', $request->class_id);
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('started_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('started_at', '<=', $request->date_to);
            }

            // For teachers, show only their sessions
            if ($request->has('view') && $request->view === 'teacher') {
                $user = Auth::user();
                $teacherId = $user->teacher?->id ?? $request->teacher_id;
                if ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                }
            }

            // Order by date (most recent first)
            $query->orderBy('started_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $sessions = $query->paginate($perPage);

            // Calculate attendance rate for each session
            $sessions->getCollection()->transform(function ($session) {
                $totalStudents = $session->attendanceRecords()->count();
                $presentCount = $session->attendanceRecords()
                    ->whereIn('status', ['present', 'late', 'online_present'])
                    ->count();
                
                $attendanceRate = $totalStudents > 0 
                    ? round(($presentCount / $totalStudents) * 100, 2) 
                    : 0;

                $session->attendance_rate = $attendanceRate;
                $session->total_students = $totalStudents;
                $session->present_count = $presentCount;

                return $session;
            });

            return $this->paginatedResponse($sessions, 'Lessons history retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve lessons history: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get detailed information about a specific lesson session
     */
    public function show(int $lessonSessionId): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();
            $tenantId = $this->getCurrentTenantId();

            if (!$schoolId) {
                return $this->errorResponse('School ID is required', 403);
            }

            $session = LessonSession::with([
                'teacher',
                'subject',
                'class',
                'schedule',
                'attendanceRecords.student',
                'behaviorRecords.student',
                'createdBy',
                'updatedBy'
            ])
            ->where('school_id', $schoolId)
            ->where('id', $lessonSessionId)
            ->first();

            if (!$session) {
                return $this->errorResponse('Lesson session not found', 404);
            }

            // Calculate detailed statistics
            $attendanceRecords = $session->attendanceRecords;
            $totalStudents = $attendanceRecords->count();
            $presentCount = $attendanceRecords->whereIn('status', ['present', 'late', 'online_present'])->count();
            $absentCount = $attendanceRecords->where('status', 'absent')->count();
            $lateCount = $attendanceRecords->where('status', 'late')->count();
            $excusedCount = $attendanceRecords->where('status', 'excused')->count();
            $unmarkedCount = $attendanceRecords->whereNull('status')->count();

            $attendanceRate = $totalStudents > 0 
                ? round(($presentCount / $totalStudents) * 100, 2) 
                : 0;

            $session->statistics = [
                'total_students' => $totalStudents,
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
                'late_count' => $lateCount,
                'excused_count' => $excusedCount,
                'unmarked_count' => $unmarkedCount,
                'attendance_rate' => $attendanceRate,
                'duration_minutes' => $session->getDurationInMinutes(),
                'duration_formatted' => $session->formatted_duration,
            ];

            return $this->successResponse($session, 'Lesson session details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve lesson session details: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Export lessons history (PDF/Excel)
     */
    public function export(Request $request, string $format): JsonResponse
    {
        try {
            // For now, return a message indicating export functionality
            // In production, implement actual PDF/Excel export using libraries like DomPDF, Laravel Excel, etc.
            
            $filters = $request->all();
            
            return $this->successResponse([
                'message' => 'Export functionality will be implemented',
                'format' => $format,
                'filters' => $filters
            ], 'Export request received');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to export lessons history: ' . $e->getMessage(),
                500
            );
        }
    }
}

