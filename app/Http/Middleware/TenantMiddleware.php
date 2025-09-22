<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $token = $this->extractToken($request);

            if (!$token) {
                return $this->unauthorizedResponse('Token not provided');
            }

            $user = $this->authenticateUser($token);

            if (!$user) {
                return $this->unauthorizedResponse('Invalid token');
            }

            // Check if user is active
            if (!$user->isActive()) {
                return $this->forbiddenResponse('User account is inactive');
            }

            // Always establish tenant context for all users
            $this->establishTenantContext($user, $request);

            return $next($request);

        } catch (TokenExpiredException $e) {
            return $this->unauthorizedResponse('Token has expired');
        } catch (TokenInvalidException $e) {
            return $this->unauthorizedResponse('Token is invalid');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Token error: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('TenantMiddleware error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
            ]);

            // Check if it's a tenant-related error
            if (str_contains($e->getMessage(), 'tenant')) {
                return $this->forbiddenResponse($e->getMessage());
            }

            return $this->serverErrorResponse('Authentication service unavailable');
        }
    }

    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return null;
        }

        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $authHeader;
    }

    private function authenticateUser(string $token)
    {
        return JWTAuth::setToken($token)->authenticate();
    }

    private function establishTenantContext($user, Request $request): void
    {
        // Check if user is super admin (role_id=1) - allow access without tenant associations
        if ($user->role_id == 1) {
            Log::info('Super admin user accessing without tenant context', [
                'user_id' => $user->id,
                'user_identifier' => $user->identifier,
                'role_id' => $user->role_id,
            ]);
            return;
        }

        // Check if user has any tenant associations
        if ($user->tenants()->count() === 0) {
            // User has no tenant associations, skip tenant context establishment
            Log::info('User has no tenant associations, skipping tenant context', [
                'user_id' => $user->id,
                'user_identifier' => $user->identifier,
            ]);
            return;
        }

        $requestedTenantId = $request->header('X-Tenant-ID') ?? $request->get('tenant_id');

        if ($requestedTenantId) {
            $this->handleTenantSwitch($user, (int)$requestedTenantId);
        } else {
            $this->setDefaultTenant($user);
        }

        $tenantId = session('tenant_id');

        if (!$tenantId) {
            throw new \Exception('No tenant context available');
        }

        if (!$user->belongsToTenant($tenantId)) {
            throw new \Exception('User does not have access to the requested tenant');
        }

        $tenant = $user->tenants()->find($tenantId);
        if (!$tenant || !$tenant->isActive()) {
            throw new \Exception('Requested tenant is not active');
        }
    }

    private function handleTenantSwitch($user, int $tenantId): void
    {
        if (!$user->belongsToTenant($tenantId)) {
            throw new \Exception('User does not have access to tenant ID: ' . $tenantId);
        }

        $success = $user->switchTenant($tenantId);

        if (!$success) {
            throw new \Exception('Failed to switch to tenant ID: ' . $tenantId);
        }

        Log::info('User switched tenant', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
        ]);
    }

    private function setDefaultTenant($user): void
    {
        $currentTenantId = session('tenant_id');

        if ($currentTenantId && $user->belongsToTenant($currentTenantId)) {
            return;
        }

        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            throw new \Exception('No accessible tenants found for user');
        }

        session(['tenant_id' => $tenant->id]);
    }

    private function unauthorizedResponse(string $message): \Symfony\Component\HttpFoundation\Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
            'code' => 401
        ], 401);
    }

    private function forbiddenResponse(string $message): \Symfony\Component\HttpFoundation\Response
    {
        return response()->json([
            'error' => 'Forbidden',
            'message' => $message,
            'code' => 403
        ], 403);
    }

    private function serverErrorResponse(string $message): \Symfony\Component\HttpFoundation\Response
    {
        return response()->json([
            'error' => 'Server Error',
            'message' => $message,
            'code' => 500
        ], 500);
    }
}
