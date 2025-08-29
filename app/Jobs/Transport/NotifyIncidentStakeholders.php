<?php

namespace App\Jobs\Transport;

use App\Models\User;
use App\Models\V1\Transport\TransportIncident;
use App\Models\V1\Transport\TransportNotification;
use App\Notifications\Transport\TransportIncidentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyIncidentStakeholders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    protected $incident;

    public function __construct(TransportIncident $incident)
    {
        $this->incident = $incident;
    }

    public function handle()
    {
        try {
            Log::info('Starting stakeholder notification for incident', [
                'incident_id' => $this->incident->id,
                'severity' => $this->incident->severity
            ]);

            // Notify transport administrators
            $this->notifyTransportAdmins();

            // Notify school administrators for high/critical incidents
            if (in_array($this->incident->severity, ['high', 'critical'])) {
                $this->notifySchoolAdmins();
            }

            // Notify transport managers
            $this->notifyTransportManagers();

            // Notify bus drivers and assistants
            $this->notifyTransportStaff();

            // Notify maintenance staff if it's a breakdown incident
            if ($this->incident->incident_type === 'breakdown') {
                $this->notifyMaintenanceStaff();
            }

            // Notify safety officers for safety-related incidents
            if (in_array($this->incident->incident_type, ['accident', 'behavioral', 'medical'])) {
                $this->notifySafetyOfficers();
            }

            Log::info('Completed stakeholder notification for incident', [
                'incident_id' => $this->incident->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify incident stakeholders', [
                'incident_id' => $this->incident->id,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function notifyTransportAdmins()
    {
        $admins = User::role('transport-admin')->get();

        foreach ($admins as $admin) {
            $this->createNotificationRecord($admin, 'transport_admin');
            $admin->notify(new TransportIncidentNotification(
                $this->incident,
                ['type' => 'admin_notification']
            ));
        }

        Log::info('Notified transport administrators', [
            'incident_id' => $this->incident->id,
            'count' => $admins->count()
        ]);
    }

    private function notifySchoolAdmins()
    {
        $schoolAdmins = User::role('school-admin')->get();

        foreach ($schoolAdmins as $admin) {
            $this->createNotificationRecord($admin, 'school_admin');
            $admin->notify(new TransportIncidentNotification(
                $this->incident,
                ['type' => 'school_admin_notification']
            ));
        }

        Log::info('Notified school administrators', [
            'incident_id' => $this->incident->id,
            'count' => $schoolAdmins->count()
        ]);
    }

    private function notifyTransportManagers()
    {
        $managers = User::role('transport-manager')->get();

        foreach ($managers as $manager) {
            $this->createNotificationRecord($manager, 'transport_manager');
            $manager->notify(new TransportIncidentNotification(
                $this->incident,
                ['type' => 'manager_notification']
            ));
        }

        Log::info('Notified transport managers', [
            'incident_id' => $this->incident->id,
            'count' => $managers->count()
        ]);
    }

    private function notifyTransportStaff()
    {
        $staff = User::role(['driver', 'assistant'])->get();

        foreach ($staff as $member) {
            $this->createNotificationRecord($member, 'transport_staff');
            $member->notify(new TransportIncidentNotification(
                $this->incident,
                ['type' => 'staff_notification']
            ));
        }

        Log::info('Notified transport staff', [
            'incident_id' => $this->incident->id,
            'count' => $staff->count()
        ]);
    }

    private function notifyMaintenanceStaff()
    {
        $maintenanceStaff = User::role('maintenance')->get();

        foreach ($maintenanceStaff as $staff) {
            $this->createNotificationRecord($staff, 'maintenance_staff');
            $staff->notify(new TransportIncidentNotification(
                $this->incident,
                ['type' => 'maintenance_notification']
            ));
        }

        Log::info('Notified maintenance staff', [
            'incident_id' => $this->incident->id,
            'count' => $maintenanceStaff->count()
        ]);
    }

    private function notifySafetyOfficers()
    {
        $safetyOfficers = User::role('safety-officer')->get();

        foreach ($safetyOfficers as $officer) {
            $this->createNotificationRecord($officer, 'safety_officer');
            $officer->notify(new TransportIncidentNotification(
                $this->incident,
                ['type' => 'safety_notification']
            ));
        }

        Log::info('Notified safety officers', [
            'incident_id' => $this->incident->id,
            'count' => $safetyOfficers->count()
        ]);
    }

    private function createNotificationRecord(User $user, string $role)
    {
        TransportNotification::create([
            'school_id' => $this->incident->school_id,
            'user_id' => $user->id,
            'notification_type' => 'incident_stakeholder',
            'channel' => 'email',
            'subject' => $this->getSubjectForRole($role),
            'message' => $this->getMessageForRole($role),
            'metadata' => [
                'incident_id' => $this->incident->id,
                'role' => $role,
                'incident_type' => $this->incident->incident_type,
                'severity' => $this->incident->severity
            ],
            'status' => 'pending'
        ]);
    }

    private function getSubjectForRole(string $role): string
    {
        $severity = ucfirst($this->incident->severity);
        $incidentType = ucwords(str_replace('_', ' ', $this->incident->incident_type));

        return match($role) {
            'transport_admin' => "🚨 {$severity} {$incidentType} Incident - Admin Action Required",
            'school_admin' => "⚠️ {$severity} Transport Incident - School Notification",
            'transport_manager' => "📋 {$severity} {$incidentType} Incident - Manager Review",
            'transport_staff' => "ℹ️ Transport Incident Update - Staff Notification",
            'maintenance_staff' => "🔧 Bus Breakdown Incident - Maintenance Required",
            'safety_officer' => "🛡️ Safety Incident Report - Officer Review Required",
            default => "📢 Transport Incident Notification"
        };
    }

    private function getMessageForRole(string $role): string
    {
        $data = [
            'incident_id' => $this->incident->id,
            'incident_type' => ucwords(str_replace('_', ' ', $this->incident->incident_type)),
            'severity' => ucfirst($this->incident->severity),
            'description' => $this->incident->description,
            'location' => $this->incident->incident_latitude ? 'GPS coordinates available' : 'Location not specified',
            'reported_by' => $this->incident->reportedBy?->name ?? 'Unknown',
            'reported_at' => $this->incident->incident_datetime->format('Y-m-d H:i:s')
        ];

        $message = "**Incident Summary:**\n";
        $message .= "- ID: {$data['incident_id']}\n";
        $message .= "- Type: {$data['incident_type']}\n";
        $message .= "- Severity: {$data['severity']}\n";
        $message .= "- Description: {$data['description']}\n";
        $message .= "- Location: {$data['location']}\n";
        $message .= "- Reported by: {$data['reported_by']}\n";
        $message .= "- Reported at: {$data['reported_at']}\n\n";

        $message .= $this->getRoleSpecificMessage($role, $data);

        return $message;
    }

    private function getRoleSpecificMessage(string $role, array $data): string
    {
        return match($role) {
            'transport_admin' => $this->getAdminMessage($data),
            'school_admin' => $this->getSchoolAdminMessage($data),
            'transport_manager' => $this->getManagerMessage($data),
            'transport_staff' => $this->getStaffMessage($data),
            'maintenance_staff' => $this->getMaintenanceMessage($data),
            'safety_officer' => $this->getSafetyOfficerMessage($data),
            default => "Please review this incident and take appropriate action."
        };
    }

    private function getAdminMessage(array $data): string
    {
        $message = "**Required Actions:**\n";
        $message .= "1. Review incident details\n";
        $message .= "2. Assign incident to appropriate staff member\n";
        $message .= "3. Monitor resolution progress\n";
        $message .= "4. Update incident status as needed\n";
        $message .= "5. Ensure proper documentation\n\n";

        $message .= "**Priority Level:** " . strtoupper($data['severity']) . "\n";

        if ($data['severity'] === 'critical') {
            $message .= "⚠️ CRITICAL: Immediate response required. Escalate to school administration.\n";
        }

        return $message;
    }

    private function getSchoolAdminMessage(array $data): string
    {
        $message = "**School Administration Notification:**\n";
        $message .= "This incident has been reported and is being handled by transport staff.\n\n";

        $message .= "**What This Means:**\n";
        $message .= "- Transport staff are aware and responding\n";
        $message .= "- Parents will be notified as appropriate\n";
        $message .= "- Updates will be provided as the situation develops\n\n";

        if ($data['severity'] === 'critical') {
            $message .= "🚨 CRITICAL INCIDENT: Consider emergency protocols and parent communication.\n";
        }

        return $message;
    }

    private function getManagerMessage(array $data): string
    {
        $message = "**Manager Review Required:**\n";
        $message .= "Please review this incident and ensure proper handling.\n\n";

        $message .= "**Review Points:**\n";
        $message .= "1. Assess incident severity and response\n";
        $message .= "2. Verify appropriate staff assignments\n";
        $message .= "3. Check compliance with safety protocols\n";
        $message .= "4. Review documentation completeness\n\n";

        return $message;
    }

    private function getStaffMessage(array $data): string
    {
        $message = "**Staff Notification:**\n";
        $message .= "A transport incident has been reported. Please be aware of the situation.\n\n";

        $message .= "**Current Status:**\n";
        $message .= "- Incident is being investigated\n";
        $message .= "- Follow any instructions from supervisors\n";
        $message .= "- Report any additional information\n\n";

        return $message;
    }

    private function getMaintenanceMessage(array $data): string
    {
        $message = "**Maintenance Required:**\n";
        $message .= "A bus breakdown incident has been reported.\n\n";

        $message .= "**Required Actions:**\n";
        $message .= "1. Review incident details\n";
        $message .= "2. Assess maintenance requirements\n";
        $message .= "3. Schedule inspection/repair\n";
        $message .= "4. Update bus status\n\n";

        return $message;
    }

    private function getSafetyOfficerMessage(array $data): string
    {
        $message = "**Safety Officer Review Required:**\n";
        $message .= "A safety-related incident has been reported.\n\n";

        $message .= "**Safety Assessment Required:**\n";
        $message .= "1. Review incident for safety implications\n";
        $message .= "2. Assess risk factors\n";
        $message .= "3. Recommend safety improvements\n";
        $message .= "4. Update safety protocols if needed\n\n";

        return $message;
    }
}
