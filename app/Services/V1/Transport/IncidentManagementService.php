<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\TransportIncident;
use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\TransportRoute;
use App\Models\User;
use App\Events\V1\Transport\TransportIncidentCreated;
// Other events will be created as needed
// use App\Events\V1\Transport\IncidentUpdated;
// use App\Events\V1\Transport\IncidentAssigned;
// use App\Events\V1\Transport\IncidentResolved;
// use App\Events\V1\Transport\EmergencyAlertTriggered;
use App\Notifications\Transport\TransportIncidentNotification;
// use App\Notifications\Transport\EmergencyAlertNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class IncidentManagementService
{
    /**
     * Get incidents with filters
     */
    public function getIncidents(array $filters = []): LengthAwarePaginator
    {
        $query = TransportIncident::with([
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
            'fleetBus:id,license_plate,internal_code',
            'transportRoute:id,route_name'
        ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (isset($filters['incident_type'])) {
            $query->where('incident_type', $filters['incident_type']);
        }

        if (isset($filters['bus_id'])) {
            $query->where('fleet_bus_id', $filters['bus_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('fleetBus', function($busQuery) use ($search) {
                      $busQuery->where('license_plate', 'like', "%{$search}%")
                               ->orWhere('internal_code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('transportRoute', function($routeQuery) use ($search) {
                      $routeQuery->where('route_name', 'like', "%{$search}%");
                  });
            });
        }

        // Default ordering
        $query->orderBy('incident_datetime', 'desc');

        return $query->paginate(15);
    }

    /**
     * Create a new incident
     */
    public function createIncident(array $data): TransportIncident
    {
        DB::beginTransaction();

        try {
            // Set default values
            $data['incident_datetime'] = $data['incident_datetime'] ?? now();
            $data['status'] = 'reported';
            $data['reported_by'] = auth()->id();

            // Create incident
            $incident = TransportIncident::create($data);

            // Load relationships
            $incident->load(['reportedBy', 'fleetBus', 'transportRoute']);

            // Fire incident created event
            event(new TransportIncidentCreated($incident));

            // Send notifications
            $this->sendIncidentNotifications($incident);

            // Handle critical incidents
            if ($incident->severity === 'critical') {
                $this->handleCriticalIncident($incident);
            }

            DB::commit();
            return $incident;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating incident: ' . $e->getMessage(), [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get incident details
     */
    public function getIncidentDetails(TransportIncident $incident): array
    {
        $incident->load([
            'reportedBy:id,name,email,phone',
            'assignedTo:id,name,email,phone',
            'fleetBus:id,license_plate,internal_code,make,model',
            'transportRoute:id,route_name,departure_time,arrival_time'
        ]);

        // Get related incidents
        $relatedIncidents = $this->getRelatedIncidents($incident);

        // Get incident timeline
        $timeline = $this->getIncidentTimeline($incident);

        // Get affected students details if any
        $affectedStudents = $this->getAffectedStudentsDetails($incident);

        return [
            'incident' => $incident,
            'related_incidents' => $relatedIncidents,
            'timeline' => $timeline,
            'affected_students' => $affectedStudents,
            'statistics' => $this->getIncidentStatistics($incident)
        ];
    }

    /**
     * Update incident
     */
    public function updateIncident(TransportIncident $incident, array $data): TransportIncident
    {
        DB::beginTransaction();

        try {
            // Check if status is being changed
            $statusChanged = isset($data['status']) && $data['status'] !== $incident->status;

            // Update incident
            $incident->update($data);

            // Load relationships
            $incident->load(['reportedBy', 'assignedTo', 'fleetBus', 'transportRoute']);

            // Fire incident updated event
            // event(new IncidentUpdated($incident));

            // Send notifications if status changed
            if ($statusChanged) {
                $this->sendStatusChangeNotifications($incident);
            }

            DB::commit();
            return $incident;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating incident: ' . $e->getMessage(), [
                'incident_id' => $incident->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Assign incident to user
     */
    public function assignIncident(TransportIncident $incident, int $assignedToId): void
    {
        DB::beginTransaction();

        try {
            $assignedUser = User::findOrFail($assignedToId);

            $incident->update([
                'assigned_to' => $assignedToId,
                'status' => 'investigating'
            ]);

            // Load relationships
            $incident->load(['reportedBy', 'assignedTo', 'fleetBus', 'transportRoute']);

            // Fire incident assigned event
            // event(new IncidentAssigned($incident, $assignedUser));

            // Send assignment notification
            $assignedUser->notify(new TransportIncidentNotification($incident, ['type' => 'assigned']));

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning incident: ' . $e->getMessage(), [
                'incident_id' => $incident->id,
                'assigned_to' => $assignedToId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Resolve incident
     */
    public function resolveIncident(TransportIncident $incident, string $resolutionNotes): void
    {
        DB::beginTransaction();

        try {
            $incident->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolution_notes' => $resolutionNotes
            ]);

            // Load relationships
            $incident->load(['reportedBy', 'assignedTo', 'fleetBus', 'transportRoute']);

            // Fire incident resolved event
            // event(new IncidentResolved($incident));

            // Send resolution notifications
            $this->sendResolutionNotifications($incident);

            // Update related statistics
            $this->updateIncidentStatistics($incident);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error resolving incident: ' . $e->getMessage(), [
                'incident_id' => $incident->id,
                'resolution_notes' => $resolutionNotes,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle emergency alert
     */
    public function handleEmergencyAlert(array $data): void
    {
        DB::beginTransaction();

        try {
            // Create emergency incident
            $emergencyIncident = TransportIncident::create([
                'school_id' => auth()->user()->school_id ?? 1,
                'fleet_bus_id' => $data['bus_id'],
                'incident_type' => 'emergency',
                'severity' => 'critical',
                'title' => 'Emergency Alert: ' . $data['emergency_type'],
                'description' => $data['description'] ?? 'Emergency situation reported',
                'incident_datetime' => now(),
                'incident_latitude' => $data['location']['lat'],
                'incident_longitude' => $data['location']['lng'],
                'reported_by' => auth()->user()->id,
                'status' => 'reported'
            ]);

            // Fire emergency alert event
            // event(new EmergencyAlertTriggered($emergencyIncident));

            // Send emergency notifications
            $this->sendEmergencyNotifications($emergencyIncident);

            // Trigger immediate response protocols
            $this->triggerEmergencyResponse($emergencyIncident);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error handling emergency alert: ' . $e->getMessage(), [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get incident statistics
     */
    public function getIncidentStatistics(TransportIncident $incident = null): array
    {
        $query = TransportIncident::query();

        if ($incident) {
            $query->where('fleet_bus_id', $incident->fleet_bus_id);
        }

        $totalIncidents = $query->count();
        $openIncidents = $query->clone()->open()->count();
        $criticalIncidents = $query->clone()->critical()->count();
        $resolvedToday = $query->clone()
            ->where('status', 'resolved')
            ->whereDate('resolved_at', today())
            ->count();

        return [
            'total_incidents' => $totalIncidents,
            'open_incidents' => $openIncidents,
            'critical_incidents' => $criticalIncidents,
            'resolved_today' => $resolvedToday,
            'resolution_rate' => $totalIncidents > 0 ? round(($totalIncidents - $openIncidents) / $totalIncidents * 100, 2) : 0
        ];
    }

    /**
     * Send incident notifications
     */
    private function sendIncidentNotifications(TransportIncident $incident): void
    {
        try {
            // Notify transport administrators
            $admins = User::role('transport-admin')->get();
            Notification::send($admins, new TransportIncidentNotification($incident, ['type' => 'created']));

            // Notify school administrators for critical incidents
            if ($incident->severity === 'critical') {
                $schoolAdmins = User::role('school-admin')->get();
                Notification::send($schoolAdmins, new TransportIncidentNotification($incident, ['type' => 'critical']));
            }

        } catch (\Exception $e) {
            Log::error('Error sending incident notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send status change notifications
     */
    private function sendStatusChangeNotifications(TransportIncident $incident): void
    {
        try {
                    if ($incident->assignedTo) {
            $incident->assignedTo->notify(new TransportIncidentNotification($incident, ['type' => 'status_changed']));
        }

        if ($incident->reportedBy) {
            $incident->reportedBy->notify(new TransportIncidentNotification($incident, ['type' => 'status_changed']));
        }

        } catch (\Exception $e) {
            Log::error('Error sending status change notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send resolution notifications
     */
    private function sendResolutionNotifications(TransportIncident $incident): void
    {
        try {
                    if ($incident->reportedBy) {
            $incident->reportedBy->notify(new TransportIncidentNotification($incident, ['type' => 'resolved']));
        }

        if ($incident->assignedTo) {
            $incident->assignedTo->notify(new TransportIncidentNotification($incident, ['type' => 'resolved']));
        }

        } catch (\Exception $e) {
            Log::error('Error sending resolution notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send emergency notifications
     */
    private function sendEmergencyNotifications(TransportIncident $incident): void
    {
        try {
            // Notify all transport staff
            $transportStaff = User::role(['transport-admin', 'driver', 'assistant'])->get();
            // Notification::send($transportStaff, new EmergencyAlertNotification($incident));

            // Notify school administrators
            $schoolAdmins = User::role('school-admin')->get();
            // Notification::send($schoolAdmins, new EmergencyAlertNotification($incident));

        } catch (\Exception $e) {
            Log::error('Error sending emergency notifications: ' . $e->getMessage());
        }
    }

    /**
     * Handle critical incident
     */
    private function handleCriticalIncident(TransportIncident $incident): void
    {
        try {
            // Auto-assign to transport manager if available
            $transportManager = User::role('transport-manager')->first();
            if ($transportManager) {
                $this->assignIncident($incident, $transportManager->id);
            }

            // Trigger immediate response protocols
            $this->triggerEmergencyResponse($incident);

        } catch (\Exception $e) {
            Log::error('Error handling critical incident: ' . $e->getMessage());
        }
    }

    /**
     * Trigger emergency response
     */
    private function triggerEmergencyResponse(TransportIncident $incident): void
    {
        try {
            // This would integrate with external emergency services
            // For now, just log the action
            Log::info('Emergency response triggered for incident: ' . $incident->id);

            // Could also trigger SMS alerts, phone calls, etc.

        } catch (\Exception $e) {
            Log::error('Error triggering emergency response: ' . $e->getMessage());
        }
    }

    /**
     * Get related incidents
     */
    private function getRelatedIncidents(TransportIncident $incident): Collection
    {
        return TransportIncident::where('fleet_bus_id', $incident->fleet_bus_id)
            ->where('id', '!=', $incident->id)
            ->where('incident_datetime', '>=', now()->subMonths(6))
            ->orderBy('incident_datetime', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Get incident timeline
     */
    private function getIncidentTimeline(TransportIncident $incident): array
    {
        $timeline = [];

        // Incident reported
        $timeline[] = [
            'action' => 'Incident Reported',
            'timestamp' => $incident->incident_datetime,
            'user' => $incident->reportedBy?->name ?? 'Unknown',
            'details' => $incident->description
        ];

        // Assigned
        if ($incident->assigned_to) {
            $timeline[] = [
                'action' => 'Incident Assigned',
                'timestamp' => $incident->updated_at,
                'user' => $incident->assignedTo?->name ?? 'Unknown',
                'details' => 'Assigned for investigation'
            ];
        }

        // Resolved
        if ($incident->resolved_at) {
            $timeline[] = [
                'action' => 'Incident Resolved',
                'timestamp' => $incident->resolved_at,
                'user' => $incident->assignedTo?->name ?? 'Unknown',
                'details' => $incident->resolution_notes
            ];
        }

        return $timeline;
    }

    /**
     * Get affected students details
     */
    private function getAffectedStudentsDetails(TransportIncident $incident): array
    {
        if (empty($incident->affected_students)) {
            return [];
        }

        // This would typically fetch student details from the SIS
        // For now, return the raw data
        return $incident->affected_students;
    }

    /**
     * Update incident statistics
     */
    private function updateIncidentStatistics(TransportIncident $incident): void
    {
        try {
            // This could update various statistics tables
            // For now, just log the resolution
            Log::info('Incident statistics updated for incident: ' . $incident->id);

        } catch (\Exception $e) {
            Log::error('Error updating incident statistics: ' . $e->getMessage());
        }
    }
}
