<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('tenant');
    }

    /**
     * Display a listing of users for the current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        // Temporarily allow access during development
        // TODO: Re-enable permission check after setting up permissions
        // if (!$user->hasTenantPermission('users.view')) {
        //     abort(403, 'Insufficient permissions');
        // }

        $users = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })
        ->when($request->get('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('identifier', 'like', "%{$search}%");
            });
        })
        ->when($request->get('status'), function ($query, $status) {
            $query->whereHas('tenants', function ($q) use ($status) {
                $q->where('tenant_id', $tenant->id)->where('status', $status);
            });
        })
        ->when($request->get('role'), function ($query, $role) use ($tenant) {
            $query->whereHas('tenants', function ($q) use ($role, $tenant) {
                $q->where('tenant_id', $tenant->id)->where('role_id', $role);
            });
        })
        ->with([
            'tenants' => function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            }
        ])
        ->orderBy('name')
        ->paginate($request->get('per_page', 15));

        // Return paginated response with UserResource
        return response()->json([
            'data' => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        // Temporarily allow access during development
        // TODO: Re-enable permission check after setting up permissions
        // if (!$currentUser->hasTenantPermission('users.view')) {
        //     abort(403, 'Insufficient permissions');
        // }

        $user = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })->findOrFail($id);

        return response()->json([
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Get users for dropdown/lookup purposes
     */
    public function lookup(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        $users = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id)
                  ->where('status', 'active');
        })
        ->when($request->get('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('identifier', 'like', "%{$search}%");
            });
        })
        ->select('id', 'name', 'identifier', 'type')
        ->orderBy('name')
        ->limit($request->get('limit', 50))
        ->get();

        return response()->json([
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->type === 'email' ? $user->identifier : null,
                    'phone' => $user->type === 'phone' ? $user->identifier : null,
                    'label' => $user->name . ' (' . $user->identifier . ')'
                ];
            })
        ]);
    }

    /**
     * Get active users for contract management
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        $users = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id)
                  ->where('status', 'active');
        })
        ->select('id', 'name', 'identifier', 'type')
        ->orderBy('name')
        ->get();

        return response()->json([
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->type === 'email' ? $user->identifier : null,
                    'phone' => $user->type === 'phone' ? $user->identifier : null,
                    'label' => $user->name . ' (' . $user->identifier . ')'
                ];
            })
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,identifier',
            'identifier' => 'nullable|string|unique:users,identifier',
            'password' => 'required|string|min:6',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'role_id' => 'nullable', // Can be string from frontend, we'll handle conversion
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'identifier' => $request->identifier ?? $request->email,
                'type' => 'email',
                'password' => Hash::make($request->password),
                'verified_at' => now(),
                'phone' => $request->phone ?? null,
            ]);

            // Attach user to tenant with status and role
            // Note: role_id column is INTEGER in database, so we can only store numeric IDs
            // If frontend sends a string role name (like 'admin'), we need to:
            // 1. Try to find the role in the roles table, or
            // 2. Don't save it (set as NULL) and let UserResource handle the mapping
            $roleId = $request->role_id ? $request->role_id : null;
            
            // If role_id is a string role name, try to find it in roles table
            if ($roleId && !is_numeric($roleId)) {
                try {
                    $role = \Illuminate\Support\Facades\DB::table('roles')
                        ->where('name', $roleId)
                        ->first();
                    $roleId = $role ? $role->id : null;
                } catch (\Exception $e) {
                    // If roles table doesn't exist or query fails, set to null
                    $roleId = null;
                }
            } elseif ($roleId && is_numeric($roleId)) {
                // If it's a numeric string (like '1'), convert to int
                $roleId = (int)$roleId;
            }
            
            $user->tenants()->attach($tenant->id, [
                'status' => $request->status ?? 'active',
                'role_id' => $roleId, // Will be null if string role name not found in DB
                'joined_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => new UserResource($user)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        // Find user within tenant
        $user = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })->findOrFail($id);

        // Validation
        // Note: email field from frontend maps to identifier in backend
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email', // Frontend may send 'email', but we use it as identifier
            'identifier' => 'sometimes|string|unique:users,identifier,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'phone' => 'nullable|string',
            'role_id' => 'nullable', // Can be string from frontend, we'll handle conversion
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update user fields
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            // Handle both 'email' and 'identifier' fields - they map to the same column
            if ($request->has('identifier')) {
                $user->identifier = $request->identifier;
            } elseif ($request->has('email')) {
                $user->identifier = $request->email; // Frontend sends 'email', but we store as 'identifier'
            }
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
            $user->save();

            // Update tenant pivot data (only fields that exist in the pivot table)
            // The pivot table 'tenant_users' has: role_id, permissions, current_tenant, joined_at, status
            // user_type is NOT in the pivot table, so we explicitly exclude it
            if ($user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                $pivotData = [];
                
                // Only add fields that exist in the pivot table
                if ($request->has('status') && !empty($request->status)) {
                    $pivotData['status'] = $request->status;
                }
                if ($request->has('role_id') && $request->role_id !== null && $request->role_id !== '') {
                    // role_id column is INTEGER, so we can only store numeric IDs
                    // If frontend sends a string role name, try to find it in roles table
                    $roleId = $request->role_id;
                    
                    if (is_numeric($roleId)) {
                        // If it's a numeric string (like '1'), convert to int
                        $pivotData['role_id'] = (int)$roleId;
                    } else {
                        // If it's a string role name (like 'admin'), try to find in roles table
                        try {
                            $role = \Illuminate\Support\Facades\DB::table('roles')
                                ->where('name', $roleId)
                                ->first();
                            if ($role) {
                                $pivotData['role_id'] = $role->id;
                            }
                            // If role not found, don't include role_id in update (skip this field)
                        } catch (\Exception $e) {
                            // If roles table doesn't exist or query fails, don't update role_id
                            // (skip this field in the update)
                        }
                    }
                }
                
                // Explicitly exclude user_type - it's not stored in the pivot table
                
                if (!empty($pivotData)) {
                    $user->tenants()->updateExistingPivot($tenant->id, $pivotData);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => new UserResource($user->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified user (soft delete from tenant)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        // Find user within tenant
        $user = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })->findOrFail($id);

        try {
            // Detach user from tenant (soft delete)
            $user->tenants()->detach($tenant->id);

            return response()->json([
                'status' => 'success',
                'message' => 'User removed from tenant successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove user',
                'error' => $e->getMessage()
            ], 422);
        }
    }
} 