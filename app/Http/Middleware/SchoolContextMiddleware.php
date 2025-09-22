<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SchoolContextService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SchoolContextMiddleware
{
    protected $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Skip if user is super admin
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return $next($request);
        }

        try {
            // Try to get school context
            $this->schoolContextService->getCurrentSchoolId();
        } catch (\RuntimeException $e) {
            // If no school context, try to set it up automatically
            Log::info('No school context found, attempting to set up automatically', [
                'user_id' => $user->id,
                'user_identifier' => $user->identifier,
                'error' => $e->getMessage()
            ]);

            $setupSuccess = $this->schoolContextService->setupSchoolContextForUser($user);

            if (!$setupSuccess) {
                // If setup failed, return a helpful error response
                return response()->json([
                    'error' => 'School context required',
                    'message' => 'Your account is not associated with any schools. Please contact an administrator.',
                    'available_schools' => $this->schoolContextService->getAvailableSchools(),
                    'setup_required' => true
                ], 403);
            }
        }

        return $next($request);
    }
}
