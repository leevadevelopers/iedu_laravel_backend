<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('tenant');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        
        $tenant = null;
        if (!$isSuperAdmin) {
            $tenant = $user->getCurrentTenant();
            if (!$tenant) {
                abort(404, 'No current tenant set');
            }
        }

        $usersQuery = User::query();

        // Search filter
        $usersQuery->when($request->get('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('identifier', 'like', "%{$search}%");
            });
        });

        // Tenant filter (for non-super_admin)
        if (!$isSuperAdmin && $tenant) {
            $usersQuery->whereHas('tenants', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            });
        }

        // Status filter
        if ($request->has('status') && $request->get('status')) {
            $status = $request->get('status');
            if (!$isSuperAdmin && $tenant) {
                $usersQuery->whereHas('tenants', function ($q) use ($tenant, $status) {
                    $q->where('tenant_id', $tenant->id)->where('status', $status);
                });
            } else if ($isSuperAdmin) {
                // For super admin, include users without tenant and those with tenant status
                $usersQuery->where(function ($q) use ($status) {
                    $q->where('status', $status)
                      ->orWhereHas('tenants', function ($tenantQuery) use ($status) {
                          $tenantQuery->where('status', $status);
                      });
                });
            }
        }

        // Role filter
        if ($request->has('role') && $request->get('role')) {
            $roleName = $request->get('role');
            \Log::info('UserController::index - Role filter applied', [
                'role_name' => $roleName,
                'is_super_admin' => $isSuperAdmin,
                'has_tenant' => $tenant !== null
            ]);
            
            // Get role ID from roles table
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
            
            if ($role) {
                $roleId = $role->id;
                \Log::info('UserController::index - Role found', [
                    'role_id' => $roleId,
                    'role_name' => $role->name
                ]);
                
                if (!$isSuperAdmin && $tenant) {
                    // For regular users, filter within their tenant
                    $usersQuery->whereHas('tenants', function ($q) use ($tenant, $roleId) {
                        $q->where('tenant_id', $tenant->id)
                          ->where('role_id', $roleId);
                    });
                } else if ($isSuperAdmin) {
                    // For super admin, filter by role across all tenants
                    $usersQuery->whereHas('tenants', function ($q) use ($roleId) {
                        $q->where('role_id', $roleId);
                    });
                }
            } else {
                \Log::warning('UserController::index - Role not found', [
                    'role_name' => $roleName
                ]);
            }
        }

        // Order newest first so recent users are visible on first page
        $usersQuery->orderByDesc('created_at');

        $perPage = $request->get('per_page', 20);
        $users = $usersQuery->paginate($perPage);

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

    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $isSuperAdmin = method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin();
        
        if (!$isSuperAdmin) {
            $tenant = $currentUser->getCurrentTenant();
            if (!$tenant) {
                abort(404, 'No current tenant set');
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:users,identifier',
            'type' => 'required|in:email,phone',
            'password' => 'required|string|min:8',
            'status' => 'nullable|in:active,inactive,suspended',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'nullable|integer|exists:roles,id',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'identifier' => $request->identifier,
            'type' => $request->type,
            'password' => Hash::make($request->password),
            'status' => $request->status ?? 'active',
            'phone' => $request->phone,
            'tenant_id' => !$isSuperAdmin && isset($tenant) ? $tenant->id : null,
        ]);

        // Attach role (Spatie) if provided
        if ($request->has('role_id') && $request->role_id) {
            $roleModel = \Spatie\Permission\Models\Role::find($request->role_id);
            if ($roleModel) {
                $user->assignRole($roleModel->name);
            }
        }

        // Attach to tenant
        if (!$isSuperAdmin && isset($tenant)) {
            $user->tenants()->attach($tenant->id, [
                'status' => $request->status ?? 'active',
                'role_id' => $request->role_id,
                'joined_at' => now(),
            ]);
        } else if ($isSuperAdmin && $request->tenant_id) {
            $user->tenants()->attach($request->tenant_id, [
                'status' => $request->status ?? 'active',
                'role_id' => $request->role_id,
                'joined_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserResource($user)
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();
        $isSuperAdmin = method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin();

        if (!$isSuperAdmin) {
            $tenant = $currentUser->getCurrentTenant();
            if (!$tenant) {
                abort(404, 'No current tenant set');
            }

            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                abort(403, 'Access denied to this user');
            }
        }

        return response()->json([
            'data' => new UserResource($user)
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();
        $isSuperAdmin = method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin();

        if (!$isSuperAdmin) {
            $tenant = $currentUser->getCurrentTenant();
            if (!$tenant) {
                abort(404, 'No current tenant set');
            }

            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                abort(403, 'Access denied to this user');
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'identifier' => 'sometimes|required|string|max:255|unique:users,identifier,' . $user->id,
            'type' => 'sometimes|required|in:email,phone',
            'password' => 'sometimes|nullable|string|min:8',
            'status' => 'nullable|in:active,inactive,suspended',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'nullable|integer|exists:roles,id',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['name', 'identifier', 'type', 'status', 'phone']);
        
        if ($request->has('password') && $request->password) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Update/attach role (Spatie) if provided
        if ($request->has('role_id') && $request->role_id) {
            $roleModel = \Spatie\Permission\Models\Role::find($request->role_id);
            if ($roleModel) {
                // syncRoles removes previous and sets new one
                $user->syncRoles([$roleModel->name]);
            }
        }

        // Update tenant pivot if applicable
        if ($request->has('role_id')) {
            if (!$isSuperAdmin && isset($tenant)) {
                $user->tenants()->updateExistingPivot($tenant->id, [
                    'role_id' => $request->role_id,
                ]);
            } else if ($isSuperAdmin && $request->tenant_id) {
                // ensure pivot exists or create
                $user->tenants()->syncWithoutDetaching([
                    $request->tenant_id => [
                        'role_id' => $request->role_id,
                        'status' => $request->status ?? 'active',
                        'joined_at' => now(),
                    ]
                ]);
            }
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new UserResource($user)
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();
        $isSuperAdmin = method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin();

        if (!$isSuperAdmin) {
            $tenant = $currentUser->getCurrentTenant();
            if (!$tenant) {
                abort(404, 'No current tenant set');
            }

            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                abort(403, 'Access denied to this user');
            }
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $currentUser = $request->user();
        $isSuperAdmin = method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin();

        $usersQuery = User::query();

        if ($query) {
            $usersQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('identifier', 'like', "%{$query}%");
            });
        }

        if (!$isSuperAdmin) {
            $tenant = $currentUser->getCurrentTenant();
            if ($tenant) {
                $usersQuery->whereHas('tenants', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id);
                });
            }
        }

        $users = $usersQuery->limit(10)->get();

        return response()->json([
            'data' => UserResource::collection($users)
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $isSuperAdmin = method_exists($currentUser, 'isSuperAdmin') && $currentUser->isSuperAdmin();

        $usersQuery = User::where('is_active', true);

        if (!$isSuperAdmin) {
            $tenant = $currentUser->getCurrentTenant();
            if ($tenant) {
                $usersQuery->whereHas('tenants', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id);
                });
            }
        }

        $users = $usersQuery->get();

        return response()->json([
            'data' => UserResource::collection($users)
        ]);
    }
}

