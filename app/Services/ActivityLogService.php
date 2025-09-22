<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ActivityLogService
{
    public function log(
        string $description,
        ?Model $subject = null,
        ?User $causer = null,
        array $properties = [],
        string $logName = 'default'
    ): Activity {
        // Add tenant_id to properties if available
        if ($tenantId = session('tenant_id')) {
            $properties['tenant_id'] = $tenantId;
        }

        $activity = activity($logName)
            ->by($causer ?? auth('api')->user())
            ->withProperties($properties);

        if ($subject) {
            $activity->on($subject);
        }

        return $activity->log($description);
    }

    public function logUserAction(
        string $action,
        ?Model $subject = null,
        array $context = [],
        ?User $user = null
    ): Activity {
        $user = $user ?? auth('api')->user();

        $properties = array_merge($context, [
            'user_context' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_role' => $user->getTenantRoleName(),
            ] : null,
            'request_context' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]
        ]);

        return $this->log($action, $subject, $user, $properties, 'user_actions');
    }

    public function logSystemEvent(
        string $event,
        ?Model $subject = null,
        array $data = []
    ): Activity {
        return $this->log($event, $subject, null, $data, 'system');
    }

    public function logSecurityEvent(
        string $event,
        array $data = [],
        ?User $user = null
    ): Activity {
        $user = $user ?? auth('api')->user();

        $properties = array_merge($data, [
            'severity' => $this->getSecurityEventSeverity($event),
            'request_data' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'headers' => request()->headers->all(),
            ]
        ]);

        return $this->log($event, null, $user, $properties, 'security');
    }

    public function getActivitiesForTenant(
        ?int $tenantId = null,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $tenantId = $tenantId ?? session('tenant_id');

        $query = Activity::forTenant($tenantId)
            ->with(['causer', 'subject'])
            ->latest();

        if (isset($filters['log_name'])) {
            $query->inLog($filters['log_name']);
        }

        if (isset($filters['event'])) {
            $query->byEvent($filters['event']);
        }

        if (isset($filters['causer_id'])) {
            $query->causedBy(User::find($filters['causer_id']));
        }

        if (isset($filters['subject_type'])) {
            $query->forSubject($filters['subject_type']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        return $query->paginate($perPage);
    }

    public function startBatch(string $description = null): string
    {
        $batchUuid = Str::uuid()->toString();

        session(['batch_uuid' => $batchUuid]);

        if ($description) {
            $this->log("Started batch operation: {$description}", null, null, [
                'batch_uuid' => $batchUuid,
                'batch_description' => $description,
            ], 'batch_operations');
        }

        return $batchUuid;
    }

    public function endBatch(string $batchUuid = null, string $summary = null): void
    {
        $batchUuid = $batchUuid ?? session('batch_uuid');

        if ($batchUuid) {
            $batchActivities = Activity::inBatch($batchUuid)->count();

            $this->log("Completed batch operation" . ($summary ? ": {$summary}" : ''), null, null, [
                'batch_uuid' => $batchUuid,
                'batch_summary' => $summary,
                'total_activities' => $batchActivities,
            ], 'batch_operations');

            session()->forget('batch_uuid');
        }
    }

    private function getSecurityEventSeverity(string $event): string
    {
        $highSeverityEvents = [
            'failed_login_attempt', 'unauthorized_access', 'permission_denied',
            'account_locked', 'suspicious_activity',
        ];

        $mediumSeverityEvents = [
            'password_changed', 'role_changed', 'permissions_modified', 'tenant_switched',
        ];

        if (in_array($event, $highSeverityEvents)) {
            return 'high';
        } elseif (in_array($event, $mediumSeverityEvents)) {
            return 'medium';
        }

        return 'low';
    }
}
