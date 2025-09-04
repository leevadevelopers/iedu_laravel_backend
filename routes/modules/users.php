<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\UserController;
use App\Http\Controllers\API\V1\UserProfileController;

/*
|--------------------------------------------------------------------------
| User Module API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for the User module. All routes are prefixed
| with 'api/v1' and require authentication middleware.
|
*/

// User Module Routes Group
Route::prefix('v1')->middleware(['auth:api', 'tenant'])->group(function () {

    // ====================================================================
    // USER MANAGEMENT ROUTES
    // ====================================================================

    Route::prefix('users')->group(function () {

        // User Lookup & Utilities (specific routes first)
        Route::get('/lookup', [UserController::class, 'lookup'])->name('users.lookup');
        Route::get('/active', [UserController::class, 'active'])->name('users.active');

        // User Search & Filters
        Route::get('/search', [UserController::class, 'index'])->name('users.search');
        Route::get('/filters', function () {
            return response()->json([
                'data' => [
                    'statuses' => ['active', 'inactive', 'pending'],
                    'roles' => ['admin', 'manager', 'team_member', 'viewer']
                ]
            ]);
        })->name('users.filters');

        // Core User CRUD (catch-all routes last)
        Route::get('/', [UserController::class, 'index'])->name('users.index');
        Route::get('/{id}', [UserController::class, 'show'])->name('users.show');
    });

    // ====================================================================
    // USER PROFILE ROUTES
    // ====================================================================

    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'getProfile'])->name('user.profile');
        Route::put('/profile', [UserProfileController::class, 'updateProfile'])->name('user.profile.update');
        Route::patch('/profile/fields', [UserProfileController::class, 'updateSpecificFields'])->name('user.profile.fields.update');
        Route::post('/avatar', [UserProfileController::class, 'uploadAvatar'])->name('user.avatar.upload');
        Route::post('/switch-tenant', [UserProfileController::class, 'switchTenant'])->name('user.switch-tenant');
        Route::get('/tenants', [UserProfileController::class, 'getUserTenants'])->name('user.tenants');
    });

    // ====================================================================
    // USER ANALYTICS ROUTES
    // ====================================================================

    Route::prefix('users-analytics')->group(function () {

        // User Statistics
        Route::get('/statistics', function () {
            $user = auth('api')->user();
            $tenant = $user->getCurrentTenant();

            $totalUsers = \App\Models\User::whereHas('tenants', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            })->count();

            $activeUsers = \App\Models\User::whereHas('tenants', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id)
                      ->where('status', 'active');
            })->count();

            return response()->json([
                'data' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'inactive_users' => $totalUsers - $activeUsers
                ]
            ]);
        })->name('users.statistics');

        // User Activity
        Route::get('/activity', function () {
            // TODO: Implement user activity tracking
            return response()->json([
                'data' => []
            ]);
        })->name('users.activity');
    });
});

/*
|--------------------------------------------------------------------------
| Route Model Binding
|--------------------------------------------------------------------------
|
| Custom route model binding for users
|
*/

// Custom route model binding for users (only for user-specific routes)
Route::bind('user', function ($value, $route) {
    // Check if the route is in the users module to avoid conflicts with tenant routes
    if (str_contains($route->getPrefix(), 'users') || str_contains($route->uri(), 'users/')) {
        $user = \App\Models\User::where('id', $value)
            ->orWhere('uuid', $value)
            ->firstOrFail();

        // Verify tenant access if tenant middleware is active
        if (auth('api')->check() && method_exists(auth('api')->user(), 'tenant_id')) {
            $currentUser = auth('api')->user();
            $tenant = $currentUser->getCurrentTenant();

            if ($tenant && !$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                abort(403, 'Access denied to this user');
            }
        }

        return $user;
    }

    // For non-user routes, just return the value as is
    return $value;
});
