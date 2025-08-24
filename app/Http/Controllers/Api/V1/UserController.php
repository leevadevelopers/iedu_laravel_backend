<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            abort(404, 'No current tenant set');
        }

        if (!$user->hasTenantPermission('users.view')) {
            abort(403, 'Insufficient permissions');
        }

        $users = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })
        ->when($request->get('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->when($request->get('status'), function ($query, $status) {
            $query->whereHas('tenants', function ($q) use ($status) {
                $q->where('status', $status);
            });
        })
        ->when($request->get('role'), function ($query, $role) {
            $query->whereHas('tenants', function ($q) use ($role) {
                $q->where('role_id', $role);
            });
        })
        ->orderBy('name')
        ->paginate($request->get('per_page', 15));

        return UserResource::collection($users);
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

        if (!$currentUser->hasTenantPermission('users.view')) {
            abort(403, 'Insufficient permissions');
        }

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
} 