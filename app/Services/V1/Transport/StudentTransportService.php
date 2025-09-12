<?php

namespace App\Services\V1\Transport;

use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Events\V1\Transport\StudentCheckedIn;
use App\Events\V1\Transport\StudentCheckedOut;
use App\Models\V1\Transport\TransportRoute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class StudentTransportService
{
    public function getSubscriptions(array $filters = []): LengthAwarePaginator
    {
        $query = StudentTransportSubscription::with([
            'student',
            'pickupStop',
            'dropoffStop',
            'transportRoute'
        ]);

        // Apply filters
        if (isset($filters['route_id'])) {
            $query->where('transport_route_id', $filters['route_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('student_number', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    public function createSubscription(array $data): StudentTransportSubscription
    {
        // Validate route capacity
        $route = TransportRoute::findOrFail($data['transport_route_id']);
        $currentBus = $route->getCurrentBus();

        if ($currentBus && $currentBus->current_capacity >= $currentBus->capacity) {
            throw new \Exception('Bus capacity exceeded for this route');
        }

        // Check for existing active subscription
        $existingSubscription = StudentTransportSubscription::where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->first();

        if ($existingSubscription) {
            throw new \Exception('Student already has an active transport subscription');
        }

        $subscription = StudentTransportSubscription::create($data);

        // Update bus capacity
        if ($currentBus) {
            $currentBus->increment('current_capacity');
        }

        activity()
            ->performedOn($subscription)
            ->log('Student subscribed to transport');

        return $subscription;
    }

    public function getSubscriptionDetails(StudentTransportSubscription $subscription): array
    {
        $subscription->load([
            'student.user',
            'pickupStop',
            'dropoffStop',
            'transportRoute.busStops',
            'transportEvents' => function($query) {
                $query->latest()->limit(10);
            }
        ]);

        return [
            'subscription' => $subscription,
            'recent_events' => $subscription->transportEvents,
            'qr_code_image' => $subscription->generateQrCodeImage(),
            'parent_contacts' => $this->getParentContacts($subscription->student),
            'attendance_stats' => $this->getAttendanceStats($subscription)
        ];
    }

    public function recordCheckin(array $data): StudentTransportEvent
    {
        // Validate subscription
        $subscription = StudentTransportSubscription::where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            throw new \Exception('Student does not have an active transport subscription');
        }

        // Check for duplicate checkin
        $existingCheckin = StudentTransportEvent::where('student_id', $data['student_id'])
            ->where('event_type', 'check_in')
            ->whereDate('event_timestamp', now())
            ->first();

        if ($existingCheckin) {
            throw new \Exception('Student already checked in today');
        }

        $eventData = array_merge($data, [
            'event_type' => 'check_in',
            'transport_route_id' => $subscription->transport_route_id,
            'event_timestamp' => $data['event_timestamp'] ?? now(),
            'recorded_by' => Auth::user()?->id,
            'is_automated' => $data['validation_method'] !== 'manual'
        ]);

        $event = StudentTransportEvent::create($eventData);

        // Fire event
        event(new StudentCheckedIn($event));

        activity()
            ->performedOn($event)
            ->log('Student checked in to transport');

        return $event;
    }

    public function recordCheckout(array $data): StudentTransportEvent
    {
        // Validate that student checked in first
        $checkinEvent = StudentTransportEvent::where('student_id', $data['student_id'])
            ->where('event_type', 'check_in')
            ->whereDate('event_timestamp', now())
            ->first();

        if (!$checkinEvent) {
            throw new \Exception('Student must check in before checking out');
        }

        // Check for duplicate checkout
        $existingCheckout = StudentTransportEvent::where('student_id', $data['student_id'])
            ->where('event_type', 'check_out')
            ->whereDate('event_timestamp', now())
            ->first();

        if ($existingCheckout) {
            throw new \Exception('Student already checked out today');
        }

        $subscription = StudentTransportSubscription::where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->first();

        $eventData = array_merge($data, [
            'event_type' => 'check_out',
            'transport_route_id' => $subscription->transport_route_id,
            'event_timestamp' => $data['event_timestamp'] ?? now(),
            'recorded_by' => Auth::user()?->id,
            'is_automated' => $data['validation_method'] !== 'manual'
        ]);

        $event = StudentTransportEvent::create($eventData);

        // Fire event
        event(new StudentCheckedOut($event));

        activity()
            ->performedOn($event)
            ->log('Student checked out of transport');

        return $event;
    }

    public function getStudentHistory(Student $student): array
    {
        $events = StudentTransportEvent::where('student_id', $student->id)
            ->with(['fleetBus', 'busStop', 'transportRoute'])
            ->orderBy('event_timestamp', 'desc')
            ->limit(50)
            ->get();

        return [
            'recent_events' => $events,
            'total_trips' => $this->getTotalTrips($student),
            'attendance_rate' => $this->calculateAttendanceRate($student),
            'favorite_stop' => $this->getFavoriteStop($student),
            'monthly_stats' => $this->getMonthlyStats($student)
        ];
    }

    public function validateQrCode(string $qrCode): array
    {
        $subscription = StudentTransportSubscription::where('qr_code', $qrCode)
            ->where('status', 'active')
            ->with(['student', 'transportRoute'])
            ->first();

        if (!$subscription) {
            throw new \Exception('Invalid or inactive QR code');
        }

        return [
            'valid' => true,
            'student' => $subscription->student,
            'subscription' => $subscription,
            'route' => $subscription->transportRoute,
            'special_needs' => $subscription->special_needs
        ];
    }

    public function generateQrCode(StudentTransportSubscription $subscription): string
    {
        return $subscription->generateQrCodeImage();
    }

    public function getBusRoster(array $data): array
    {
        $date = $data['date'] ?? now()->toDateString();

        $subscriptions = StudentTransportSubscription::where('transport_route_id', $data['route_id'])
            ->where('status', 'active')
            ->with(['student', 'pickupStop', 'dropoffStop'])
            ->get();

        $events = StudentTransportEvent::where('fleet_bus_id', $data['bus_id'])
            ->where('transport_route_id', $data['route_id'])
            ->whereDate('event_timestamp', $date)
            ->get()
            ->groupBy('student_id');

        $roster = $subscriptions->map(function($subscription) use ($events) {
            $studentEvents = $events->get($subscription->student_id, collect());

            return [
                'student' => $subscription->student,
                'pickup_stop' => $subscription->pickupStop,
                'dropoff_stop' => $subscription->dropoffStop,
                'checked_in' => $studentEvents->where('event_type', 'check_in')->isNotEmpty(),
                'checked_out' => $studentEvents->where('event_type', 'check_out')->isNotEmpty(),
                'special_needs' => $subscription->special_needs,
                'events' => $studentEvents
            ];
        });

        return [
            'date' => $date,
            'total_students' => $roster->count(),
            'checked_in' => $roster->where('checked_in', true)->count(),
            'checked_out' => $roster->where('checked_out', true)->count(),
            'no_shows' => $roster->where('checked_in', false)->count(),
            'students' => $roster->values()
        ];
    }

    private function getParentContacts(Student $student): array
    {
        // This would get parent contacts from the student's family relationships
        return []; // Mock implementation
    }

    private function getAttendanceStats(StudentTransportSubscription $subscription): array
    {
        $totalDays = $subscription->created_at->diffInDays(now());
        $attendedDays = StudentTransportEvent::where('student_id', $subscription->student_id)
            ->where('event_type', 'check_in')
            ->where('event_timestamp', '>=', $subscription->created_at)
            ->selectRaw('DATE(event_timestamp) as event_date')
            ->distinct()
            ->count();

        return [
            'total_days' => $totalDays,
            'attended_days' => $attendedDays,
            'attendance_rate' => $totalDays > 0 ? round(($attendedDays / $totalDays) * 100, 2) : 0
        ];
    }

    private function getTotalTrips(Student $student): int
    {
        return StudentTransportEvent::where('student_id', $student->id)
            ->where('event_type', 'check_in')
            ->count();
    }

    private function calculateAttendanceRate(Student $student): float
    {
        $subscription = $student->transportSubscriptions()->where('status', 'active')->first();
        if (!$subscription) return 0;

        $stats = $this->getAttendanceStats($subscription);
        return $stats['attendance_rate'];
    }

    private function getFavoriteStop(Student $student): ?array
    {
        $stopUsage = StudentTransportEvent::where('student_id', $student->id)
            ->where('event_type', 'check_in')
            ->with('busStop')
            ->get()
            ->groupBy('bus_stop_id')
            ->map(function($events) {
                return $events->count();
            })
            ->sortDesc()
            ->first();

        return $stopUsage ? ['stop' => $stopUsage, 'count' => $stopUsage] : null;
    }

    private function getMonthlyStats(Student $student): array
    {
        $stats = [];
        for ($i = 0; $i < 6; $i++) {
            $month = now()->subMonths($i);
            $tripCount = StudentTransportEvent::where('student_id', $student->id)
                ->where('event_type', 'check_in')
                ->whereYear('event_timestamp', $month->year)
                ->whereMonth('event_timestamp', $month->month)
                ->count();

            $stats[] = [
                'month' => $month->format('M Y'),
                'trips' => $tripCount
            ];
        }

        return array_reverse($stats);
    }
}
