<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\TenantResource;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private ActivityLogService $activityLogService)
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            $this->activityLogService->logSecurityEvent('failed_login_attempt', [
                'email' => $request->get('email'),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = auth('api')->user();
        
        if (!$user->isActive()) {
            return response()->json([
                'error' => 'Account is inactive. Please contact administrator.'
            ], 403);
        }

        // Handle tenant context
        if ($request->has('tenant_id')) {
            $tenantId = $request->get('tenant_id');
            if (!$user->belongsToTenant($tenantId)) {
                return response()->json([
                    'error' => 'You do not have access to the requested organization.'
                ], 403);
            }
            $user->switchTenant($tenantId);
        } else {
            // Set to first available tenant
            $firstTenant = $user->activeTenants()->first();
            if ($firstTenant) {
                session(['tenant_id' => $firstTenant->id]);
            }
        }

        $user->updateLastLogin();

        $this->activityLogService->logUserAction('user_logged_in', $user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => new UserResource($user),
            'current_tenant' => $user->getCurrentTenant() ? 
                new TenantResource($user->getCurrentTenant()) : null,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255|unique:tenants,name',
        ]);

        try {
            $user = User::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => Hash::make($request->get('password')),
                'is_active' => true,
            ]);

            // Create organization and make user owner
            $tenant = app(\App\Services\TenantService::class)->createTenant([
                'name' => $request->get('organization_name'),
                'is_active' => true,
            ], $user);

            $token = JWTAuth::fromUser($user);

            $this->activityLogService->logUserAction('user_registered', $user);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => new UserResource($user),
                'current_tenant' => new TenantResource($tenant),
                'message' => 'Registration successful'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        
        return response()->json([
            'user' => new UserResource($user),
            'current_tenant' => $user->getCurrentTenant() ? 
                new TenantResource($user->getCurrentTenant()) : null,
            'tenant_context' => $user->getTenantContext(),
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth('api')->user();
        
        $this->activityLogService->logUserAction('user_logged_out', $user);
        
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh(): JsonResponse
    {
        return response()->json([
            'access_token' => JWTAuth::refresh(JWTAuth::getToken()),
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ]);
    }
}