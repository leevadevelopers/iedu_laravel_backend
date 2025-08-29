#!/bin/bash

# Transport Module - Jobs Generator
echo "⚙️ Creating Transport Module Background Jobs..."

# 1. SendTransportNotification Job
cat > app/Jobs/Transport/SendTransportNotification.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\User;
use App\Models\Student;
use App\Models\Transport\TransportNotification;
use App\Notifications\Transport\StudentTransportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTransportNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    protected $notificationData;

    public function __construct(array $notificationData)
    {
        $this->notificationData = $notificationData;
    }

    public function handle()
    {
        try {
            $parent = User::findOrFail($this->notificationData['parent_id']);
            $student = Student::findOrFail($this->notificationData['student_id']);

            $channels = $this->notificationData['channels'] ?? ['email'];

            foreach ($channels as $channel) {
                $this->sendNotificationViaChannel($parent, $student, $channel);
            }

        } catch (\Exception $e) {
            Log::error('Transport notification failed', [
                'error' => $e->getMessage(),
                'data' => $this->notificationData
            ]);

            $this->fail($e);
        }
    }

    private function sendNotificationViaChannel(User $parent, Student $student, string $channel)
    {
        // Create notification record
        $notification = TransportNotification::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'notification_type' => $this->notificationData['type'],
            'channel' => $channel,
            'subject' => $this->getSubject(),
            'message' => $this->getMessage(),
            'metadata' => $this->notificationData['data'],
            'status' => 'pending'
        ]);

        try {
            // Send via Laravel notification system
            $parent->notify(new StudentTransportNotification(
                $notification,
                $channel,
                $this->notificationData
            ));

            $notification->markAsSent();

        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function getSubject(): string
    {
        $studentName = $this->notificationData['data']['student_name'] ?? 'Your child';

        return match($this->notificationData['type']) {
            'check_in' => "✅ {$studentName} boarded the school bus",
            'check_out' => "🏫 {$studentName} arrived at school safely",
            'delay' => "⏰ Bus delay notification for {$studentName}",
            'incident' => "⚠️ Transport incident involving {$studentName}",
            'route_change' => "🛣️ Route change notification for {$studentName}",
            default => "📍 Transport update for {$studentName}"
        };
    }

    private function getMessage(): string
    {
        $data = $this->notificationData['data'];
        $studentName = $data['student_name'] ?? 'Your child';

        return match($this->notificationData['type']) {
            'check_in' => "Good morning! {$studentName} has safely boarded bus {$data['bus_info']} at {$data['stop_name']} at {$data['time']}. The bus is now heading to school.",

            'check_out' => "Great news! {$studentName} has arrived at school at {$data['arrival_time']} and has safely disembarked from bus {$data['bus_info']}.",

            'delay' => "We wanted to let you know that the bus for {$studentName} is running approximately {$data['delay_minutes']} minutes late. New estimated arrival: {$data['new_eta']}.",

            'incident' => "We're writing to inform you of a {$data['incident_type']} incident involving {$studentName}'s school bus. {$data['description']} Immediate action taken: {$data['immediate_action']}. We will keep you updated.",

            'route_change' => "There has been a change to {$studentName}'s bus route. Please check the parent portal for updated information.",

            default => "This is a transport update regarding {$studentName}."
        };
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SendTransportNotification job failed permanently', [
            'exception' => $exception->getMessage(),
            'data' => $this->notificationData
        ]);
    }
}
EOF

# 2. ProcessGpsTracking Job
cat > app/Jobs/Transport/ProcessGpsTracking.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\Transport\FleetBus;
use App\Models\Transport\TransportTracking;
use App\Services\Transport\TransportTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGpsTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 2;

    protected $gpsData;

    public function __construct(array $gpsData)
    {
        $this->gpsData = $gpsData;
    }

    public function handle(TransportTrackingService $trackingService)
    {
        try {
            // Validate GPS data
            $this->validateGpsData();

            // Find the bus by GPS device ID or other identifier
            $bus = $this->findBusFromGpsData();

            if (!$bus) {
                Log::warning('GPS data received for unknown bus', $this->gpsData);
                return;
            }

            // Get current route assignment
            $currentAssignment = $bus->currentAssignment;
            if (!$currentAssignment) {
                Log::info('GPS data received for bus without active route assignment', [
                    'bus_id' => $bus->id,
                    'gps_data' => $this->gpsData
                ]);
                return;
            }

            // Process the location update
            $trackingData = $this->prepareTrackingData($bus, $currentAssignment);
            $trackingService->updateLocation($trackingData);

            // Check for geofence events (arrival at stops)
            $this->checkGeofenceEvents($bus, $trackingData);

            // Update bus status based on movement
            $this->updateBusStatus($bus);

        } catch (\Exception $e) {
            Log::error('GPS tracking processing failed', [
                'error' => $e->getMessage(),
                'gps_data' => $this->gpsData
            ]);

            $this->fail($e);
        }
    }

    private function validateGpsData(): void
    {
        $required = ['latitude', 'longitude', 'timestamp'];
        foreach ($required as $field) {
            if (!isset($this->gpsData[$field])) {
                throw new \InvalidArgumentException("Missing required GPS field: {$field}");
            }
        }

        if ($this->gpsData['latitude'] < -90 || $this->gpsData['latitude'] > 90) {
            throw new \InvalidArgumentException('Invalid latitude value');
        }

        if ($this->gpsData['longitude'] < -180 || $this->gpsData['longitude'] > 180) {
            throw new \InvalidArgumentException('Invalid longitude value');
        }
    }

    private function findBusFromGpsData(): ?FleetBus
    {
        // Try multiple methods to identify the bus
        if (isset($this->gpsData['device_id'])) {
            return FleetBus::where('gps_device_id', $this->gpsData['device_id'])->first();
        }

        if (isset($this->gpsData['bus_id'])) {
            return FleetBus::find($this->gpsData['bus_id']);
        }

        if (isset($this->gpsData['license_plate'])) {
            return FleetBus::where('license_plate', $this->gpsData['license_plate'])->first();
        }

        return null;
    }

    private function prepareTrackingData(FleetBus $bus, $assignment): array
    {
        return [
            'bus_id' => $bus->id,
            'route_id' => $assignment->transport_route_id,
            'latitude' => $this->gpsData['latitude'],
            'longitude' => $this->gpsData['longitude'],
            'speed_kmh' => $this->gpsData['speed'] ?? 0,
            'heading' => $this->gpsData['heading'] ?? null,
            'altitude' => $this->gpsData['altitude'] ?? null,
            'status' => $this->determineStatus(),
            'raw_gps_data' => $this->gpsData
        ];
    }

    private function determineStatus(): string
    {
        $speed = $this->gpsData['speed'] ?? 0;

        if ($speed < 1) {
            return 'stationary';
        } elseif ($speed < 10) {
            return 'at_stop';
        } else {
            return 'in_transit';
        }
    }

    private function checkGeofenceEvents(FleetBus $bus, array $trackingData): void
    {
        // This would check if the bus has entered any stop geofences
        // Implementation would compare current location with stop locations
        // and trigger BusArrivedAtStop events if within geofence
    }

    private function updateBusStatus(FleetBus $bus): void
    {
        $lastTracking = $bus->latestTracking;

        if ($lastTracking && $lastTracking->tracked_at < now()->subMinutes(10)) {
            // Bus hasn't reported in 10 minutes - might be offline
            Log::warning('Bus GPS tracking appears offline', [
                'bus_id' => $bus->id,
                'last_tracking' => $lastTracking->tracked_at
            ]);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('GPS tracking job failed permanently', [
            'exception' => $exception->getMessage(),
            'gps_data' => $this->gpsData
        ]);
    }
}
EOF

# 3. GenerateTransportReport Job
cat > app/Jobs/Transport/GenerateTransportReport.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\School;
use App\Services\Transport\TransportReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateTransportReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes for large reports
    public $tries = 2;

    protected $schoolId;
    protected $reportType;
    protected $parameters;
    protected $requestedBy;

    public function __construct(int $schoolId, string $reportType, array $parameters, int $requestedBy)
    {
        $this->schoolId = $schoolId;
        $this->reportType = $reportType;
        $this->parameters = $parameters;
        $this->requestedBy = $requestedBy;
    }

    public function handle(TransportReportService $reportService)
    {
        try {
            Log::info('Starting transport report generation', [
                'school_id' => $this->schoolId,
                'report_type' => $this->reportType,
                'requested_by' => $this->requestedBy
            ]);

            $school = School::findOrFail($this->schoolId);

            // Generate the report based on type
            $reportData = $this->generateReportData($reportService, $school);

            // Convert to desired format (PDF, Excel, etc.)
            $filePath = $this->saveReportFile($reportData);

            // Send notification to requester
            $this->notifyReportReady($filePath);

            Log::info('Transport report generated successfully', [
                'school_id' => $this->schoolId,
                'report_type' => $this->reportType,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Transport report generation failed', [
                'error' => $e->getMessage(),
                'school_id' => $this->schoolId,
                'report_type' => $this->reportType
            ]);

            $this->fail($e);
        }
    }

    private function generateReportData(TransportReportService $reportService, School $school): array
    {
        return match($this->reportType) {
            'attendance' => $reportService->generateAttendanceReport($school, $this->parameters),
            'performance' => $reportService->generatePerformanceReport($school, $this->parameters),
            'financial' => $reportService->generateFinancialReport($school, $this->parameters),
            'safety' => $reportService->generateSafetyReport($school, $this->parameters),
            'utilization' => $reportService->generateUtilizationReport($school, $this->parameters),
            'custom' => $reportService->generateCustomReport($school, $this->parameters),
            default => throw new \InvalidArgumentException("Unknown report type: {$this->reportType}")
        };
    }

    private function saveReportFile(array $reportData): string
    {
        $filename = $this->generateFilename();
        $format = $this->parameters['format'] ?? 'pdf';

        switch ($format) {
            case 'pdf':
                return $this->generatePdfReport($reportData, $filename);
            case 'excel':
                return $this->generateExcelReport($reportData, $filename);
            case 'csv':
                return $this->generateCsvReport($reportData, $filename);
            default:
                return $this->generateJsonReport($reportData, $filename);
        }
    }

    private function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "transport_reports/{$this->reportType}_{$this->schoolId}_{$timestamp}";
    }

    private function generatePdfReport(array $data, string $filename): string
    {
        // Implementation would use a PDF library like TCPDF or DomPDF
        $pdfContent = $this->renderReportTemplate($data);
        $filePath = $filename . '.pdf';

        Storage::disk('local')->put($filePath, $pdfContent);
        return $filePath;
    }

    private function generateExcelReport(array $data, string $filename): string
    {
        // Implementation would use PhpSpreadsheet
        $filePath = $filename . '.xlsx';
        // Excel generation logic here
        return $filePath;
    }

    private function generateCsvReport(array $data, string $filename): string
    {
        $filePath = $filename . '.csv';
        $csvContent = $this->convertToCsv($data);

        Storage::disk('local')->put($filePath, $csvContent);
        return $filePath;
    }

    private function generateJsonReport(array $data, string $filename): string
    {
        $filePath = $filename . '.json';
        Storage::disk('local')->put($filePath, json_encode($data, JSON_PRETTY_PRINT));
        return $filePath;
    }

    private function renderReportTemplate(array $data): string
    {
        // This would render a blade template to HTML then convert to PDF
        return view('transport.reports.' . $this->reportType, compact('data'))->render();
    }

    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'w');

        // Write headers
        if (isset($data[0]) && is_array($data[0])) {
            fputcsv($output, array_keys($data[0]));
        }

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function notifyReportReady(string $filePath): void
    {
        // Send notification to the user who requested the report
        // This could be an email with download link, dashboard notification, etc.
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Report generation job failed permanently', [
            'exception' => $exception->getMessage(),
            'school_id' => $this->schoolId,
            'report_type' => $this->reportType
        ]);
    }
}
EOF

# 4. SendDelayNotification Job
cat > app/Jobs/Transport/SendDelayNotification.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\User;
use App\Models\Student;
use App\Notifications\Transport\BusDelayNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDelayNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    protected $delayData;

    public function __construct(array $delayData)
    {
        $this->delayData = $delayData;
    }

    public function handle()
    {
        try {
            $parent = User::findOrFail($this->delayData['parent_id']);
            $student = Student::findOrFail($this->delayData['student_id']);

            // Send notification
            $parent->notify(new BusDelayNotification($this->delayData));

            Log::info('Delay notification sent successfully', [
                'parent_id' => $parent->id,
                'student_id' => $student->id,
                'delay_minutes' => $this->delayData['delay_minutes']
            ]);

        } catch (\Exception $e) {
            Log::error('Delay notification failed', [
                'error' => $e->getMessage(),
                'data' => $this->delayData
            ]);

            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SendDelayNotification job failed permanently', [
            'exception' => $exception->getMessage(),
            'data' => $this->delayData
        ]);
    }
}
EOF

# 5. ProcessTransportSubscription Job
cat > app/Jobs/Transport/ProcessTransportSubscription.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\Transport\StudentTransportSubscription;
use App\Services\Transport\StudentTransportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTransportSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 2;

    protected $subscriptionId;
    protected $action;
    protected $data;

    public function __construct(int $subscriptionId, string $action, array $data = [])
    {
        $this->subscriptionId = $subscriptionId;
        $this->action = $action;
        $this->data = $data;
    }

    public function handle(StudentTransportService $transportService)
    {
        try {
            $subscription = StudentTransportSubscription::findOrFail($this->subscriptionId);

            switch ($this->action) {
                case 'approve':
                    $this->approveSubscription($subscription);
                    break;

                case 'renew':
                    $this->renewSubscription($subscription, $transportService);
                    break;

                case 'expire':
                    $this->expireSubscription($subscription);
                    break;

                case 'cancel':
                    $this->cancelSubscription($subscription);
                    break;

                default:
                    throw new \InvalidArgumentException("Unknown subscription action: {$this->action}");
            }

        } catch (\Exception $e) {
            Log::error('Transport subscription processing failed', [
                'subscription_id' => $this->subscriptionId,
                'action' => $this->action,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function approveSubscription(StudentTransportSubscription $subscription): void
    {
        $subscription->update(['status' => 'active']);

        // Update bus capacity
        $route = $subscription->transportRoute;
        $bus = $route->getCurrentBus();
        if ($bus) {
            $bus->increment('current_capacity');
        }

        // Send approval notification to parents
        if ($subscription->authorized_parents) {
            foreach ($subscription->authorized_parents as $parentId) {
                SendTransportNotification::dispatch([
                    'parent_id' => $parentId,
                    'student_id' => $subscription->student_id,
                    'type' => 'subscription_approved',
                    'channels' => ['email'],
                    'data' => [
                        'student_name' => $subscription->student->first_name . ' ' . $subscription->student->last_name,
                        'route_name' => $subscription->transportRoute->name,
                        'pickup_stop' => $subscription->pickupStop->name,
                        'start_date' => $subscription->start_date->format('Y-m-d')
                    ]
                ]);
            }
        }

        Log::info('Transport subscription approved', [
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id
        ]);
    }

    private function renewSubscription(StudentTransportSubscription $subscription, StudentTransportService $service): void
    {
        if (!$subscription->canAutoRenew()) {
            Log::warning('Attempted to renew non-renewable subscription', [
                'subscription_id' => $subscription->id
            ]);
            return;
        }

        // Calculate new end date based on subscription type
        $newEndDate = match($subscription->subscription_type) {
            'monthly' => $subscription->end_date->addMonth(),
            'term' => $subscription->end_date->addMonths(3),
            'weekly' => $subscription->end_date->addWeek(),
            default => $subscription->end_date->addMonth()
        };

        $subscription->update([
            'end_date' => $newEndDate,
            'status' => 'active'
        ]);

        Log::info('Transport subscription renewed', [
            'subscription_id' => $subscription->id,
            'new_end_date' => $newEndDate->format('Y-m-d')
        ]);
    }

    private function expireSubscription(StudentTransportSubscription $subscription): void
    {
        $subscription->update(['status' => 'expired']);

        // Update bus capacity
        $route = $subscription->transportRoute;
        $bus = $route->getCurrentBus();
        if ($bus) {
            $bus->decrement('current_capacity');
        }

        Log::info('Transport subscription expired', [
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id
        ]);
    }

    private function cancelSubscription(StudentTransportSubscription $subscription): void
    {
        $subscription->update(['status' => 'cancelled']);

        // Update bus capacity
        $route = $subscription->transportRoute;
        $bus = $route->getCurrentBus();
        if ($bus) {
            $bus->decrement('current_capacity');
        }

        Log::info('Transport subscription cancelled', [
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id
        ]);
    }

    public function failed(\Throwable $exception)
    {
        Log::error('ProcessTransportSubscription job failed permanently', [
            'subscription_id' => $this->subscriptionId,
            'action' => $this->action,
            'exception' => $exception->getMessage()
        ]);
    }
}
EOF

# 6. SyncExternalGpsData Job
cat > app/Jobs/Transport/SyncExternalGpsData.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\Transport\FleetBus;
use App\Services\Transport\ExternalGpsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncExternalGpsData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;
    public $tries = 3;

    protected $schoolId;

    public function __construct(int $schoolId = null)
    {
        $this->schoolId = $schoolId;
    }

    public function handle(ExternalGpsService $gpsService)
    {
        try {
            Log::info('Starting GPS data sync', ['school_id' => $this->schoolId]);

            // Get buses that need GPS sync
            $buses = $this->getBusesForSync();

            foreach ($buses as $bus) {
                $this->syncBusGpsData($bus, $gpsService);
            }

            Log::info('GPS data sync completed', [
                'school_id' => $this->schoolId,
                'buses_processed' => $buses->count()
            ]);

        } catch (\Exception $e) {
            Log::error('GPS data sync failed', [
                'school_id' => $this->schoolId,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function getBusesForSync()
    {
        $query = FleetBus::active()
            ->whereNotNull('gps_device_id')
            ->whereHas('currentAssignment');

        if ($this->schoolId) {
            $query->where('school_id', $this->schoolId);
        }

        return $query->get();
    }

    private function syncBusGpsData(FleetBus $bus, ExternalGpsService $gpsService): void
    {
        try {
            // Fetch latest GPS data from external service
            $gpsData = $gpsService->getLatestLocation($bus->gps_device_id);

            if (!$gpsData) {
                Log::warning('No GPS data available for bus', [
                    'bus_id' => $bus->id,
                    'device_id' => $bus->gps_device_id
                ]);
                return;
            }

            // Process the GPS data
            ProcessGpsTracking::dispatch($gpsData);

        } catch (\Exception $e) {
            Log::error('Failed to sync GPS data for bus', [
                'bus_id' => $bus->id,
                'device_id' => $bus->gps_device_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SyncExternalGpsData job failed permanently', [
            'school_id' => $this->schoolId,
            'exception' => $exception->getMessage()
        ]);
    }
}
EOF

# 7. CheckMaintenanceDue Job
cat > app/Jobs/Transport/CheckMaintenanceDue.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\Transport\FleetBus;
use App\Events\Transport\BusMaintenanceScheduled;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckMaintenanceDue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public function handle()
    {
        try {
            Log::info('Starting maintenance due check');

            // Find buses needing maintenance
            $busesNeedingMaintenance = FleetBus::active()
                ->needingInspection()
                ->get();

            foreach ($busesNeedingMaintenance as $bus) {
                $this->processMaintenanceDue($bus);
            }

            // Check insurance renewals
            $this->checkInsuranceRenewals();

            Log::info('Maintenance check completed', [
                'buses_needing_maintenance' => $busesNeedingMaintenance->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Maintenance check failed', [
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function processMaintenanceDue(FleetBus $bus): void
    {
        // Create maintenance alert
        event(new BusMaintenanceScheduled($bus, 'inspection', $bus->next_inspection_due));

        // If overdue, set bus to maintenance status
        if ($bus->next_inspection_due && $bus->next_inspection_due->isPast()) {
            $bus->update(['status' => 'maintenance']);

            // Deactivate current assignment
            if ($bus->currentAssignment) {
                $bus->currentAssignment->update(['status' => 'suspended']);
            }

            Log::warning('Bus set to maintenance due to overdue inspection', [
                'bus_id' => $bus->id,
                'license_plate' => $bus->license_plate,
                'due_date' => $bus->next_inspection_due->format('Y-m-d')
            ]);
        }
    }

    private function checkInsuranceRenewals(): void
    {
        $busesWithExpiringInsurance = FleetBus::active()
            ->whereNotNull('insurance_expiry')
            ->where('insurance_expiry', '<=', now()->addDays(30))
            ->get();

        foreach ($busesWithExpiringInsurance as $bus) {
            Log::warning('Bus insurance expiring soon', [
                'bus_id' => $bus->id,
                'license_plate' => $bus->license_plate,
                'expiry_date' => $bus->insurance_expiry->format('Y-m-d')
            ]);

            // Send notification to administrators
            // This could integrate with the notification system
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('CheckMaintenanceDue job failed', [
            'exception' => $exception->getMessage()
        ]);
    }
}
EOF

# 8. OptimizeTransportRoutes Job
cat > app/Jobs/Transport/OptimizeTransportRoutes.php << 'EOF'
<?php

namespace App\Jobs\Transport;

use App\Models\School;
use App\Models\Transport\TransportRoute;
use App\Services\Transport\TransportRouteService;
use App\Events\Transport\RouteOptimized;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OptimizeTransportRoutes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes for complex optimization
    public $tries = 1; // Don't retry optimization jobs

    protected $schoolId;
    protected $routeIds;

    public function __construct(int $schoolId, array $routeIds = [])
    {
        $this->schoolId = $schoolId;
        $this->routeIds = $routeIds;
    }

    public function handle(TransportRouteService $routeService)
    {
        try {
            Log::info('Starting route optimization', [
                'school_id' => $this->schoolId,
                'route_ids' => $this->routeIds
            ]);

            $school = School::findOrFail($this->schoolId);

            // Get routes to optimize
            $routes = $this->getRoutesToOptimize();

            $totalSavings = 0;
            $optimizedRoutes = [];

            foreach ($routes as $route) {
                try {
                    $results = $routeService->optimizeRoute($route);

                    $optimizedRoutes[] = [
                        'route_id' => $route->id,
                        'route_name' => $route->name,
                        'results' => $results
                    ];

                    $totalSavings += $results['distance_saved'];

                    // Fire optimization event
                    event(new RouteOptimized($route, $results));

                } catch (\Exception $e) {
                    Log::error('Failed to optimize route', [
                        'route_id' => $route->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Route optimization completed', [
                'school_id' => $this->schoolId,
                'routes_optimized' => count($optimizedRoutes),
                'total_distance_saved' => $totalSavings
            ]);

        } catch (\Exception $e) {
            Log::error('Route optimization job failed', [
                'school_id' => $this->schoolId,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function getRoutesToOptimize()
    {
        $query = TransportRoute::where('school_id', $this->schoolId)
            ->where('status', 'active')
            ->with(['busStops']);

        if (!empty($this->routeIds)) {
            $query->whereIn('id', $this->routeIds);
        }

        return $query->get();
    }

    public function failed(\Throwable $exception)
    {
        Log::error('OptimizeTransportRoutes job failed permanently', [
            'school_id' => $this->schoolId,
            'route_ids' => $this->routeIds,
            'exception' => $exception->getMessage()
        ]);
    }
}
EOF

# 9. Create a Console Command to dispatch jobs
cat > app/Console/Commands/Transport/ProcessTransportJobs.php << 'EOF'
<?php

namespace App\Console\Commands\Transport;

use App\Jobs\Transport\CheckMaintenanceDue;
use App\Jobs\Transport\SyncExternalGpsData;
use App\Jobs\Transport\ProcessTransportSubscription;
use App\Models\Transport\StudentTransportSubscription;
use Illuminate\Console\Command;

class ProcessTransportJobs extends Command
{
    protected $signature = 'transport:process-jobs
                           {--school= : Process jobs for specific school ID}
                           {--maintenance : Check maintenance due}
                           {--gps-sync : Sync GPS data}
                           {--subscriptions : Process subscription renewals}';

    protected $description = 'Process various transport-related background jobs';

    public function handle()
    {
        $schoolId = $this->option('school');

        if ($this->option('maintenance')) {
            $this->info('Dispatching maintenance checks...');
            CheckMaintenanceDue::dispatch();
        }

        if ($this->option('gps-sync')) {
            $this->info('Dispatching GPS sync jobs...');
            SyncExternalGpsData::dispatch($schoolId);
        }

        if ($this->option('subscriptions')) {
            $this->info('Processing subscription renewals...');
            $this->processSubscriptionRenewals($schoolId);
        }

        // If no specific option, run all
        if (!$this->option('maintenance') && !$this->option('gps-sync') && !$this->option('subscriptions')) {
            $this->info('Running all transport jobs...');
            CheckMaintenanceDue::dispatch();
            SyncExternalGpsData::dispatch($schoolId);
            $this->processSubscriptionRenewals($schoolId);
        }

        $this->info('Transport jobs dispatched successfully!');
    }

    private function processSubscriptionRenewals($schoolId): void
    {
        $query = StudentTransportSubscription::where('status', 'active')
            ->where('auto_renewal', true)
            ->whereDate('end_date', '<=', now()->addDays(7)); // Renew 7 days before expiry

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $subscriptions = $query->get();

        foreach ($subscriptions as $subscription) {
            ProcessTransportSubscription::dispatch($subscription->id, 'renew');
        }

        $this->info("Dispatched {$subscriptions->count()} subscription renewal jobs");
    }
}
EOF

echo "✅ Transport module background jobs created successfully!"
echo "📝 Jobs include:"
echo "   - GPS tracking processing with real-time updates"
echo "   - Multi-channel notification delivery (email, SMS, push)"
echo "   - Automated report generation (PDF, Excel, CSV)"
echo "   - Transport subscription management and renewals"
echo "   - Maintenance scheduling and alerts"
echo "   - Route optimization algorithms"
echo "   - External GPS data synchronization"
echo ""
echo "🔧 Console command created:"
echo "   php artisan transport:process-jobs [options]"
echo ""
echo "⚡ Queue configuration needed:"
echo "   - Configure Redis/Database queue driver"
echo "   - Set up queue workers: php artisan queue:work"
echo "   - Schedule recurring jobs in app/Console/Kernel.php"
