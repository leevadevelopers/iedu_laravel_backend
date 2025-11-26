<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Services\V1\Transport\StudentTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentTransportController extends Controller
{
    protected $studentTransportService;

    public function __construct(StudentTransportService $studentTransportService)
    {
        $this->studentTransportService = $studentTransportService;
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['route_id', 'status', 'search']);
            $subscriptions = $this->studentTransportService->getSubscriptions($filters);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Student subscriptions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'required|exists:students,id',
            'pickup_stop_id' => 'required|exists:bus_stops,id',
            'dropoff_stop_id' => 'required|exists:bus_stops,id',
            'transport_route_id' => 'required|exists:transport_routes,id',
            'subscription_type' => 'required|in:daily,weekly,monthly,term',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'monthly_fee' => 'nullable|numeric|min:0',
            'auto_renewal' => 'nullable|boolean',
            'authorized_parents' => 'nullable|array',
            'authorized_parents.*' => 'exists:users,id',
            'special_needs' => 'nullable|string',
            'rfid_card_id' => 'nullable|string|max:50|unique:student_transport_subscriptions,rfid_card_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $subscription = $this->studentTransportService->createSubscription($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $subscription->load(['student', 'pickupStop', 'dropoffStop', 'transportRoute']),
                'message' => 'Student subscribed to transport successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            $subscriptionData = $this->studentTransportService->getSubscriptionDetails($subscription);

            return response()->json([
                'success' => true,
                'data' => $subscriptionData,
                'message' => 'Subscription details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'required|exists:students,id',
            'fleet_bus_id' => 'required|exists:fleet_buses,id',
            'bus_stop_id' => 'required|exists:bus_stops,id',
            'validation_method' => 'required|in:qr_code,rfid,manual,facial_recognition',
            'validation_data' => 'nullable|string',
            'event_latitude' => 'nullable|numeric|between:-90,90',
            'event_longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'event_timestamp' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $event = $this->studentTransportService->recordCheckin($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Student checked in successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording checkin: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'required|exists:students,id',
            'fleet_bus_id' => 'required|exists:fleet_buses,id',
            'bus_stop_id' => 'required|exists:bus_stops,id',
            'validation_method' => 'required|in:qr_code,rfid,manual,facial_recognition',
            'validation_data' => 'nullable|string',
            'event_latitude' => 'nullable|numeric|between:-90,90',
            'event_longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'event_timestamp' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $event = $this->studentTransportService->recordCheckout($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Student checked out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording checkout: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStudentHistory(Student $student): JsonResponse
    {
        try {
            $history = $this->studentTransportService->getStudentHistory($student);

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Student transport history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving history: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validateQrCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code is required',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->studentTransportService->validateQrCode($request->qr_code);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'QR Code validated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR Code: ' . $e->getMessage()
            ], 400);
        }
    }

    public function generateQrCode(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            $qrCodeImage = $this->studentTransportService->generateQrCode($subscription);

            return response()->json([
                'success' => true,
                'data' => [
                    'qr_code' => $subscription->qr_code,
                    'image' => $qrCodeImage
                ],
                'message' => 'QR Code generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating QR Code: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBusRoster(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bus_id' => 'required|exists:fleet_buses,id',
            'route_id' => 'required|exists:transport_routes,id',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $roster = $this->studentTransportService->getBusRoster($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $roster,
                'message' => 'Bus roster retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving roster: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if student has active transport subscription
     */
    public function checkSubscriptionStatus(Student $student): JsonResponse
    {
        try {
            $subscription = StudentTransportSubscription::where('student_id', $student->id)
                ->where('status', 'active')
                ->with(['pickupStop', 'dropoffStop', 'transportRoute'])
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student does not have an active transport subscription',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'is_active' => $subscription->isActive(),
                    'remaining_days' => $subscription->getRemainingDays(),
                    'can_auto_renew' => $subscription->canAutoRenew()
                ],
                'message' => 'Student has active transport subscription'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking subscription status: ' . $e->getMessage()
            ], 500);
        }
    }
}
