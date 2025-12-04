<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Extract token from Authorization header
            $token = $request->header('Authorization');

            Log::info('TenantMiddleware: Starting request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'has_token' => !empty($token),
            ]);

            if (!$token) {
                Log::warning('TenantMiddleware: No token provided');
                return response()->json(['error' => 'Token not provided'], 401);
            }

            // Remove 'Bearer ' prefix if present
            $token = str_replace('Bearer ', '', $token);

            // Authenticate the user using the token
            $user = JWTAuth::setToken($token)->authenticate();

            if (!$user) {
                Log::warning('TenantMiddleware: User authentication failed');
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            Log::info('TenantMiddleware: User authenticated', [
                'user_id' => $user->id,
                'user_identifier' => $user->identifier,
                'user_email' => $user->email ?? null,
                'user_name' => $user->name,
            ]);

            // Check if user is active
            if (!$user->isActive()) {
                Log::warning('TenantMiddleware: User account is inactive', [
                    'user_id' => $user->id,
                ]);
                return response()->json(['error' => 'User account is inactive'], 403);
            }

            // Check if user has tenants
            $tenantCount = $user->tenants()->count();
            $allTenantIds = $user->tenants()->pluck('tenants.id')->toArray();
            
            Log::info('TenantMiddleware: Checking user tenants', [
                'user_id' => $user->id,
                'tenant_count' => $tenantCount,
                'tenant_ids' => $allTenantIds,
                'session_tenant_id' => session('tenant_id'),
                'header_tenant_id' => $request->header('X-Tenant-ID'),
            ]);

            if ($user->tenants()->exists()) {
                // Priority 1: Check X-Tenant-ID header (for API clients)
                $requestedTenantId = $request->header('X-Tenant-ID');
                
                if ($requestedTenantId) {
                    $requestedTenantId = (int) $requestedTenantId;
                    Log::info('TenantMiddleware: X-Tenant-ID header found', [
                        'requested_tenant_id' => $requestedTenantId,
                        'user_belongs_to_tenant' => $user->belongsToTenant($requestedTenantId),
                    ]);
                    
                    // Validate user has access to requested tenant
                    if ($user->belongsToTenant($requestedTenantId)) {
                        session(['tenant_id' => $requestedTenantId]);
                        Log::info('TenantMiddleware: Set tenant from header', [
                            'tenant_id' => $requestedTenantId,
                        ]);
                    } else {
                        Log::warning('TenantMiddleware: User does not have access to requested tenant', [
                            'user_id' => $user->id,
                            'requested_tenant_id' => $requestedTenantId,
                            'user_tenant_ids' => $allTenantIds,
                        ]);
                        return response()->json(['error' => 'User does not have access to the requested tenant'], 403);
                    }
                } else {
                    // Priority 2: Use existing session tenant_id
                    // Priority 3: Use user's current tenant (wherePivot('current_tenant', true))
                    // Priority 4: Fallback to first tenant
                    $sessionTenantId = session('tenant_id');
                    $currentTenantId = $user->tenants()->wherePivot('current_tenant', true)->value('tenants.id');
                    $firstTenantId = $user->tenants()->first()?->id;
                    
                    $tenantId = $sessionTenantId ?? $currentTenantId ?? $firstTenantId;
                    
                    Log::info('TenantMiddleware: Resolving tenant ID', [
                        'session_tenant_id' => $sessionTenantId,
                        'current_tenant_id' => $currentTenantId,
                        'first_tenant_id' => $firstTenantId,
                        'resolved_tenant_id' => $tenantId,
                    ]);
                    
                    if ($tenantId) {
                        session(['tenant_id' => $tenantId]);
                        Log::info('TenantMiddleware: Set tenant from fallback', [
                            'tenant_id' => $tenantId,
                            'source' => $sessionTenantId ? 'session' : ($currentTenantId ? 'current_tenant' : 'first_tenant'),
                        ]);
                    } else {
                        Log::error('TenantMiddleware: Could not resolve tenant ID', [
                            'user_id' => $user->id,
                            'session_tenant_id' => $sessionTenantId,
                            'current_tenant_id' => $currentTenantId,
                            'first_tenant_id' => $firstTenantId,
                            'all_tenant_ids' => $allTenantIds,
                        ]);
                    }
                }
            } else {
                // User has no tenant associations
                // Check if user is super_admin - they might not need tenant associations
                $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : false;
                
                Log::error('TenantMiddleware: User has no tenant associations', [
                    'user_id' => $user->id,
                    'user_identifier' => $user->identifier,
                    'user_email' => $user->email ?? null,
                    'is_super_admin' => $isSuperAdmin,
                    'tenant_count' => $tenantCount,
                    'header_tenant_id' => $request->header('X-Tenant-ID'),
                ]);

                // Check tenant_users pivot table directly for debugging
                $pivotCount = \Illuminate\Support\Facades\DB::table('tenant_users')
                    ->where('user_id', $user->id)
                    ->count();
                
                Log::info('TenantMiddleware: Direct pivot table check', [
                    'user_id' => $user->id,
                    'pivot_table_count' => $pivotCount,
                ]);

                // Handle super admin - they work across all tenants, no tenant context needed
                if ($isSuperAdmin) {
                    // Super admin can optionally set a tenant context via header for filtering,
                    // but it's not required - they can see everything
                    $headerTenantId = $request->header('X-Tenant-ID');
                    
                    Log::info('TenantMiddleware: Super admin detected - no tenant required', [
                        'user_id' => $user->id,
                        'header_tenant_id' => $headerTenantId,
                        'note' => 'Super admin works across all tenants',
                    ]);
                    
                    // Optionally set tenant context if header provided (for filtering purposes)
                    if ($headerTenantId) {
                        $headerTenantId = (int) $headerTenantId;
                        $tenantExists = \Illuminate\Support\Facades\DB::table('tenants')
                            ->where('id', $headerTenantId)
                            ->exists();
                        
                        if ($tenantExists) {
                            session(['tenant_id' => $headerTenantId]);
                            Log::info('TenantMiddleware: Super admin set optional tenant context', [
                                'user_id' => $user->id,
                                'tenant_id' => $headerTenantId,
                                'note' => 'This is optional - super admin can still see all tenants',
                            ]);
                        }
                    }
                    
                    // Super admin always allowed - no tenant required
                    return $next($request);
                }
                
                return response()->json([
                    'error' => 'No tenant associated with this user.',
                    'debug' => [
                        'user_id' => $user->id,
                        'tenant_count' => $tenantCount,
                        'pivot_count' => $pivotCount,
                        'is_super_admin' => $isSuperAdmin,
                        'header_tenant_id' => $request->header('X-Tenant-ID'),
                    ]
                ], 403);
            }

            // If we reach here, user has tenants and tenant_id is set
            Log::info('TenantMiddleware: Request allowed to proceed', [
                'user_id' => $user->id,
                'session_tenant_id' => session('tenant_id'),
            ]);
            return $next($request);

        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token has expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token is invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 401);
        } catch (\Exception $e) {
            Log::error('TenantMiddleware error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Check if it's a tenant-related error
            if (str_contains($e->getMessage(), 'tenant')) {
                return response()->json(['error' => $e->getMessage()], 403);
            }

            return response()->json(['error' => 'Authentication service unavailable'], 500);
    }
    }
}
