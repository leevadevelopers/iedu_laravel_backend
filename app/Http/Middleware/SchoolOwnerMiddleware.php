<?php

namespace App\Http\Middleware;

use App\Services\SchoolContextService;
use App\Models\V1\SIS\School\SchoolUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SchoolOwnerMiddleware
{
    public function __construct(
        private SchoolContextService $schoolContextService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Authentication required.',
                'error' => 'unauthorized'
            ], 401);
        }

        // Super admin can access (optional - remove if school owners should not have super admin access)
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        // Get current school
        try {
            $schoolId = $this->schoolContextService->getCurrentSchoolId();
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'No school context available. Please select a school.',
                'error' => 'no_school_context'
            ], 403);
        }

        // Check if user has 'owner' role in school_users pivot for this school
        $schoolUser = SchoolUser::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->first();

        if (!$schoolUser) {
            // Also check if user has 'school_owner' role via Spatie permissions
            if (method_exists($user, 'hasRole') && $user->hasRole('school_owner')) {
                // Verify they have access to this specific school
                $hasAccess = $user->schools()
                    ->where('schools.id', $schoolId)
                    ->wherePivot('status', 'active')
                    ->exists();

                if ($hasAccess) {
                    return $next($request);
                }
            }

            return response()->json([
                'message' => 'Unauthorized. School owner access required.',
                'error' => 'forbidden'
            ], 403);
        }

        return $next($request);
    }
}

