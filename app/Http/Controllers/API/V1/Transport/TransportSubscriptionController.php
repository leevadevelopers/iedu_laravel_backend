<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Models\V1\SIS\Student\Student;
use App\Services\V1\Transport\StudentTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TransportSubscriptionController extends Controller
{
    protected $studentTransportService;

    public function __construct(StudentTransportService $studentTransportService)
    {
        $this->studentTransportService = $studentTransportService;
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of transport subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['route_id', 'status', 'search', 'student_id', 'school_id']);
            $subscriptions = $this->studentTransportService->getSubscriptions($filters);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Transport subscriptions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created transport subscription
     */
    public function store(Request $request): JsonResponse
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
                'message' => 'Transport subscription created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transport subscription
     */
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

    /**
     * Update the specified transport subscription
     */
    public function update(Request $request, StudentTransportSubscription $subscription): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pickup_stop_id' => 'sometimes|exists:bus_stops,id',
            'dropoff_stop_id' => 'sometimes|exists:bus_stops,id',
            'transport_route_id' => 'sometimes|exists:transport_routes,id',
            'subscription_type' => 'sometimes|in:daily,weekly,monthly,term',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:start_date',
            'monthly_fee' => 'nullable|numeric|min:0',
            'auto_renewal' => 'nullable|boolean',
            'authorized_parents' => 'nullable|array',
            'authorized_parents.*' => 'exists:users,id',
            'special_needs' => 'nullable|string',
            'rfid_card_id' => 'nullable|string|max:50|unique:student_transport_subscriptions,rfid_card_id,' . $subscription->id
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $subscription->update($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $subscription->load(['student', 'pickupStop', 'dropoffStop', 'transportRoute']),
                'message' => 'Subscription updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified transport subscription
     */
    public function destroy(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            // Soft delete the subscription
            $subscription->delete();

            activity()
                ->performedOn($subscription)
                ->log('Transport subscription deleted');

            return response()->json([
                'success' => true,
                'message' => 'Subscription deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a transport subscription
     */
    public function activate(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            if ($subscription->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is already active'
                ], 400);
            }

            $subscription->update(['status' => 'active']);

            activity()
                ->performedOn($subscription)
                ->log('Transport subscription activated');

            return response()->json([
                'success' => true,
                'data' => $subscription,
                'message' => 'Subscription activated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error activating subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspend a transport subscription
     */
    public function suspend(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            if ($subscription->status === 'suspended') {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is already suspended'
                ], 400);
            }

            $subscription->update(['status' => 'suspended']);

            activity()
                ->performedOn($subscription)
                ->log('Transport subscription suspended');

            return response()->json([
                'success' => true,
                'data' => $subscription,
                'message' => 'Subscription suspended successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error suspending subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a transport subscription
     */
    public function cancel(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            if ($subscription->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is already cancelled'
                ], 400);
            }

            $subscription->update(['status' => 'cancelled']);

            activity()
                ->performedOn($subscription)
                ->log('Transport subscription cancelled');

            return response()->json([
                'success' => true,
                'data' => $subscription,
                'message' => 'Subscription cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscriptions by student
     */
    public function getByStudent(Student $student): JsonResponse
    {
        try {
            $subscriptions = StudentTransportSubscription::where('student_id', $student->id)
                ->with(['pickupStop', 'dropoffStop', 'transportRoute'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Student subscriptions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving student subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscriptions by route
     */
    public function getByRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:transport_routes,id',
            'status' => 'nullable|in:active,suspended,cancelled,pending_approval'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = StudentTransportSubscription::where('transport_route_id', $request->route_id)
                ->with(['student', 'pickupStop', 'dropoffStop']);

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $subscriptions = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Route subscriptions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving route subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expiring subscriptions
     */
    public function getExpiring(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:90'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $days = $request->days ?? 30;
            $subscriptions = StudentTransportSubscription::expiring($days)
                ->with(['student', 'pickupStop', 'dropoffStop', 'transportRoute'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Expiring subscriptions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving expiring subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate QR code for subscription
     */
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

    /**
     * Renew subscription
     */
    public function renew(Request $request, StudentTransportSubscription $subscription): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'end_date' => 'required|date|after:today',
            'monthly_fee' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($subscription->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active subscriptions can be renewed'
                ], 400);
            }

            $subscription->update([
                'end_date' => $request->end_date,
                'monthly_fee' => $request->monthly_fee ?? $subscription->monthly_fee
            ]);

            activity()
                ->performedOn($subscription)
                ->log('Transport subscription renewed');

            return response()->json([
                'success' => true,
                'data' => $subscription,
                'message' => 'Subscription renewed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error renewing subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_subscriptions' => StudentTransportSubscription::count(),
                'active_subscriptions' => StudentTransportSubscription::where('status', 'active')->count(),
                'suspended_subscriptions' => StudentTransportSubscription::where('status', 'suspended')->count(),
                'cancelled_subscriptions' => StudentTransportSubscription::where('status', 'cancelled')->count(),
                'pending_subscriptions' => StudentTransportSubscription::where('status', 'pending_approval')->count(),
                'expiring_this_month' => StudentTransportSubscription::expiring(30)->count(),
                'monthly_revenue' => StudentTransportSubscription::where('status', 'active')
                    ->sum('monthly_fee'),
                'subscription_types' => StudentTransportSubscription::selectRaw('subscription_type, COUNT(*) as count')
                    ->groupBy('subscription_type')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Subscription statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
