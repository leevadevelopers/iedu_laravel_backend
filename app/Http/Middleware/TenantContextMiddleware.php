<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantContextMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Ensure user has tenant context
        if (auth()->check() && !auth()->user()->tenant_id) {
            return response()->json(['message' => 'Tenant context required'], 403);
        }

        return $next($request);
    }
}
