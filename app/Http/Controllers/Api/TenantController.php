<?php

namespace App\Http\Controllers\Api;

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
        // $this->middleware('auth:api');
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

    public function store(CreateTenantRequest $request): JsonResponse
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

    public function users(Request $request): AnonymousResourceCollection
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
}