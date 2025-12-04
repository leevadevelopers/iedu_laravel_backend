<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
        $this->middleware('auth:api');
        $this->middleware('tenant');
    }

    /**
     * Display a listing of activity logs for the current tenant
     * Super admin can view all logs or filter by tenant_id
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        
        $tenantId = null;
        
        if ($isSuperAdmin) {
            // Super admin can view all logs or filter by tenant_id if provided
            if ($request->has('tenant_id')) {
                $tenantId = (int) $request->get('tenant_id');
            }
            // If no tenant_id provided, $tenantId remains null and all logs will be shown
        } else {
            // Regular users must have a tenant
            $tenant = $user->getCurrentTenant();
            if (!$tenant) {
                abort(404, 'No current tenant set');
            }
            $tenantId = $tenant->id;
        }

        // Temporarily allow access during development
        // TODO: Re-enable permission check after setting up permissions
        // if (!$user->hasTenantPermission('users.view_activity_logs')) {
        //     abort(403, 'Insufficient permissions');
        // }

        // Build filters from request
        $filters = [];
        
        if ($request->has('log_name')) {
            $filters['log_name'] = $request->get('log_name');
        }
        
        if ($request->has('event')) {
            $filters['event'] = $request->get('event');
        }
        
        if ($request->has('causer_id')) {
            $filters['causer_id'] = $request->get('causer_id');
        }
        
        if ($request->has('subject_type')) {
            $filters['subject_type'] = $request->get('subject_type');
        }
        
        if ($request->has('date_from')) {
            $filters['date_from'] = $request->get('date_from');
        }
        
        if ($request->has('date_to')) {
            $filters['date_to'] = $request->get('date_to');
        }
        
        if ($request->has('search')) {
            $filters['search'] = $request->get('search');
        }

        $perPage = $request->get('per_page', 20);
        $activities = $this->activityLogService->getActivitiesForTenant(
            $tenantId, // null for super_admin (all logs), or specific tenant_id
            $filters,
            $perPage
        );

        // Format response - get items from paginator
        $formattedActivities = $activities->getCollection()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'log_name' => $activity->log_name,
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                    'email' => $activity->causer->email,
                ] : null,
                'subject' => $activity->subject ? [
                    'id' => $activity->subject->id,
                    'type' => class_basename($activity->subject),
                    'name' => $activity->subject->name ?? $activity->subject->title ?? null,
                ] : null,
                'properties' => $activity->properties ?? [],
                'created_at' => $activity->created_at->toISOString(),
                'updated_at' => $activity->updated_at ? $activity->updated_at->toISOString() : null,
            ];
        });

        return response()->json([
            'data' => $formattedActivities->values()->all(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'last_page' => $activities->lastPage(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ]
        ]);
    }
}

