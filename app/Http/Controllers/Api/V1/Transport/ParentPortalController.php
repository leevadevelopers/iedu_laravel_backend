<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use App\Services\V1\Transport\ParentPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ParentPortalController extends Controller
{
    protected $parentPortalService;

    public function __construct(ParentPortalService $parentPortalService)
    {
        $this->parentPortalService = $parentPortalService;
        $this->middleware('auth:api');
        $this->middleware('permission:view-own-students');
    }

    public function dashboard()
    {
        try {
            $dashboard = $this->parentPortalService->getDashboard(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Dashboard data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStudentStatus(Student $student): JsonResponse
    {
        // Verify parent has access to this student
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to student'
            ], 403);
        }

        try {
            $status = $this->parentPortalService->getStudentTransportStatus($student);

            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => 'Student status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBusLocation(Student $student): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $location = $this->parentPortalService->getStudentBusLocation($student);

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Bus location retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving location: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTransportHistory(Student $student, Request $request): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $history = $this->parentPortalService->getTransportHistory($student, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Transport history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving history: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'required|boolean',
            'sms_notifications' => 'required|boolean',
            'push_notifications' => 'required|boolean',
            'whatsapp_notifications' => 'required|boolean',
            'notification_types' => 'required|array',
            'notification_types.*' => 'in:check_in,check_out,delay,incident,route_change'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preferences = $this->parentPortalService->updateNotificationPreferences(
                auth()->user(),
                $validator->validated()
            );

            return response()->json([
                'success' => true,
                'data' => $preferences,
                'message' => 'Notification preferences updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,sent,delivered,failed,read',
            'type' => 'nullable|in:check_in,check_out,delay,incident,route_change',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notifications = $this->parentPortalService->getNotifications(
                auth()->user(),
                $validator->validated()
            );

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markNotificationRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required|exists:transport_notifications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->parentPortalService->markNotificationAsRead($request->notification_id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking notification: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRouteMap(Student $student): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $routeMap = $this->parentPortalService->getRouteMap($student);

            return response()->json([
                'success' => true,
                'data' => $routeMap,
                'message' => 'Route map retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving route map: ' . $e->getMessage()
            ], 500);
        }
    }

    public function requestStopChange(Student $student, Request $request): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_pickup_stop_id' => 'nullable|exists:bus_stops,id',
            'new_dropoff_stop_id' => 'nullable|exists:bus_stops,id',
            'reason' => 'required|string|max:500',
            'effective_date' => 'required|date|after:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $request = $this->parentPortalService->requestStopChange($student, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $request,
                'message' => 'Stop change request submitted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting request: ' . $e->getMessage()
            ], 500);
        }
    }
}
