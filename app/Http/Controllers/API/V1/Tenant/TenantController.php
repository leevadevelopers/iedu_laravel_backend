<?php

namespace App\Http\Controllers\API\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\CreateTenantRequest;
use App\Http\Requests\AddUserToTenantRequest;
use App\Models\Settings\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    public function __construct(private TenantService $tenantService)
    {
        $this->middleware('auth:api');
        $this->middleware('tenant')->except(['index', 'store', 'switch']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tenants = $user->activeTenants()
            ->withPivot(['role_id', 'current_tenant', 'status', 'joined_at'])
            ->when($request->get('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('tenants.name')
            ->get()
            ->map(function ($tenant) {
                $tenant->user_role = $tenant->pivot->role_id;
                $tenant->is_current = $tenant->pivot->current_tenant;
                $tenant->user_status = $tenant->pivot->status;
                $tenant->joined_at = $tenant->pivot->joined_at;
                return $tenant;
            });

        return TenantResource::collection($tenants);
    }

    public function store(CreateTenantRequest $request)
    {
        $user = $request->user();

        try {
            $tenant = $this->tenantService->createTenant($request->validated(), $user);

            return response()->json([
                'data' => new TenantResource($tenant),
                'message' => 'Tenant created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create tenant',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.view')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $tenantData = new TenantResource($tenant);
        $tenantData->additional([
            'user_context' => $user->getTenantContext(),
            'stats' => $this->tenantService->getTenantStats($tenant)
        ]);

        return response()->json(['data' => $tenantData]);
    }

    public function showById(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        // Check if user has access to this tenant
        $tenant = $user->activeTenants()->find($id);
        
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found or access denied'], 404);
        }

        return response()->json(['data' => new TenantResource($tenant)]);
    }

    public function switch(Request $request): JsonResponse
    {
        $request->validate(['tenant_id' => 'required|integer|exists:tenants,id']);

        $user = $request->user();
        $tenantId = $request->get('tenant_id');

        try {
            $success = $this->tenantService->switchUserTenant($user, $tenantId);

            if (!$success) {
                return response()->json(['error' => 'Unable to switch to the requested tenant'], 403);
            }

            $newTenant = $user->getCurrentTenant();

            return response()->json([
                'data' => new TenantResource($newTenant),
                'user_context' => $user->getTenantContext(),
                'message' => "Switched to tenant: {$newTenant->name}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to switch tenant',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function users(Request $request)
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('users.view')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $users = $this->tenantService->getTenantUsers($tenant, $request->get('status', 'active'));

        return UserResource::collection($users);
    }

    public function addUser(AddUserToTenantRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        try {
            $targetUser = User::findOrFail($request->get('user_id'));

            $this->tenantService->addUserToTenant(
                $tenant,
                $targetUser,
                $request->get('role', 'team_member'),
                false,
                $request->get('custom_permissions', [])
            );

            return response()->json([
                'data' => new UserResource($targetUser),
                'message' => 'User added to tenant successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add user to tenant',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get current tenant settings
     */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.manage_settings')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $settings = $tenant->settings ?? [];
        
        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'settings' => $settings
            ]
        ]);
    }

    /**
     * Update current tenant settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.manage_settings')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.branding' => 'sometimes|array',
            'settings.branding.logo' => 'sometimes|string|url',
            'settings.branding.favicon' => 'sometimes|string|url',
            'settings.branding.colors' => 'sometimes|array',
            'settings.branding.colors.primary' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'settings.branding.colors.secondary' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'settings.branding.colors.accent' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'settings.branding.company' => 'sometimes|array',
            'settings.branding.company.name' => 'sometimes|string|max:255',
            'settings.branding.company.shortName' => 'sometimes|string|max:100',
            'settings.branding.company.description' => 'sometimes|string|max:500',
            'settings.branding.company.website' => 'sometimes|string|url',
            'settings.branding.company.email' => 'sometimes|email',
            'settings.branding.company.phone' => 'sometimes|string|max:20',
            'settings.branding.company.address' => 'sometimes|string|max:500',
            'settings.ui' => 'sometimes|array',
            'settings.ui.theme' => 'sometimes|in:light,dark,auto',
            'settings.ui.layout' => 'sometimes|in:classy,compact,modern',
            'settings.ui.density' => 'sometimes|in:compact,standard,comfortable',
            'settings.ui.animations' => 'sometimes|boolean',
            'settings.app' => 'sometimes|array',
            'settings.app.timezone' => 'sometimes|string|max:50',
            'settings.app.dateFormat' => 'sometimes|string|max:20',
            'settings.app.timeFormat' => 'sometimes|string|max:20',
            'settings.app.currency' => 'sometimes|string|max:10',
            'settings.app.language' => 'sometimes|string|max:10',
            'settings.app.locale' => 'sometimes|string|max:10',
        ]);

        try {
            $updatedTenant = $this->tenantService->updateTenantSettings($tenant, $request->get('settings'));

            return response()->json([
                'data' => new TenantResource($updatedTenant),
                'message' => 'Tenant settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update tenant settings',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get tenant branding configuration
     */
    public function branding(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        $settings = $tenant->settings ?? [];
        $branding = $settings['branding'] ?? [];

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'branding' => $branding
            ]
        ]);
    }

    /**
     * Update tenant branding configuration
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.manage_settings')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'branding' => 'required|array',
            'branding.logo' => 'sometimes|string|url',
            'branding.favicon' => 'sometimes|string|url',
            'branding.colors' => 'sometimes|array',
            'branding.colors.primary' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'branding.colors.secondary' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'branding.colors.accent' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'branding.company' => 'sometimes|array',
            'branding.company.name' => 'sometimes|string|max:255',
            'branding.company.shortName' => 'sometimes|string|max:100',
            'branding.company.description' => 'sometimes|string|max:500',
            'branding.company.website' => 'sometimes|string|url',
            'branding.company.email' => 'sometimes|email',
            'branding.company.phone' => 'sometimes|string|max:20',
            'branding.company.address' => 'sometimes|string|max:500',
        ]);

        try {
            $currentSettings = $tenant->settings ?? [];
            
            // Ensure currentSettings is an array
            if (!is_array($currentSettings)) {
                $currentSettings = [];
            }
            
            // Ensure branding is an array before merging
            $currentBranding = $currentSettings['branding'] ?? [];
            if (!is_array($currentBranding)) {
                $currentBranding = [];
            }
            
            $newBranding = $request->get('branding');
            if (!is_array($newBranding)) {
                $newBranding = [];
            }
            
            $currentSettings['branding'] = array_merge($currentBranding, $newBranding);
            
            $updatedTenant = $this->tenantService->updateTenantSettings($tenant, $currentSettings);

            return response()->json([
                'data' => new TenantResource($updatedTenant),
                'message' => 'Tenant branding updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update tenant branding',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
