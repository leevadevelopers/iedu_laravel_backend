<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Constants\ErrorCodes;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\UserResource;
use App\Http\Resources\TenantResource;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(private ActivityLogService $activityLogService)
    {
        $this->middleware('auth:api');
    }

    /**
     * Get the authenticated user's profile
     */
    public function getProfile(): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = auth('api')->user();

            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    ErrorCodes::UNAUTHENTICATED,
                    null,
                    401
                );
            }

            // Get tenant context if available
            $currentTenant = null;
            $tenantContext = null;

            try {
                $currentTenant = $user->getCurrentTenant();
                if ($currentTenant) {
                    $tenantContext = $this->getTenantContext($user);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get tenant context in getProfile: ' . $e->getMessage());
            }

            return ApiResponse::success([
                'user' => new UserResource($user),
                'current_tenant' => $currentTenant ? new TenantResource($currentTenant) : null,
                'tenant_context' => $tenantContext,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user profile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve profile',
                ErrorCodes::OPERATION_FAILED,
                null,
                500
            );
        }
    }

    /**
     * Update the authenticated user's profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return ApiResponse::error(
                'User not authenticated',
                401
            );
        }

        $data = $request->validated();

        if (empty($data)) {
            return ApiResponse::error(
                'No data provided to update',
                422
            );
        }

        // Se o identifier mudou, verifica a senha atual
        if (isset($data['identifier']) && $data['identifier'] !== $user->identifier) {
            if (!isset($data['current_password']) || !Hash::check($data['current_password'], $user->password)) {
                return ApiResponse::error('Current password is incorrect', 422);
            }
        }

        // Atualiza apenas campos enviados
        $user->fill($data);
        $user->save();

        $this->activityLogService->logUserAction('profile_updated', $user, [
            'changes' => $data,
        ]);

        // Retorna dados atualizados
        return ApiResponse::success([
            'user' => new \App\Http\Resources\UserResource($user),
        ]);
    }


    /**
     * Upload profile photo
     */
    public function uploadProfilePhoto(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = auth('api')->user();

            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    ErrorCodes::UNAUTHENTICATED,
                    null,
                    401
                );
            }

            // Validate the uploaded file
            $validator = Validator::make($request->all(), [
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            ]);

            if ($validator->fails()) {
                return ApiResponse::error(
                    'Validation failed',
                    ErrorCodes::VALIDATION_FAILED,
                    $validator->errors(),
                    422
                );
            }

            // Delete old photo if exists
            if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            // Store new photo
            $photoPath = $request->file('photo')->store(
                'profile-photos/' . $user->id,
                'public'
            );

            // Update user's profile_photo_path
            $user->profile_photo_path = $photoPath;
            $user->save();

            // Log the update
            $this->activityLogService->logUserAction('profile_photo_uploaded', $user, [
                'photo_path' => $photoPath,
            ]);

            // Get tenant context if available
            $currentTenant = null;
            $tenantContext = null;

            try {
                $currentTenant = $user->getCurrentTenant();
                if ($currentTenant) {
                    $tenantContext = $this->getTenantContext($user);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get tenant context in uploadProfilePhoto: ' . $e->getMessage());
            }

            return ApiResponse::success([
                'user' => new UserResource($user),
                'photo_url' => asset('storage/' . $photoPath),
                'current_tenant' => $currentTenant ? new TenantResource($currentTenant) : null,
                'tenant_context' => $tenantContext,
            ], null, null, 200);

        } catch (\Exception $e) {
            Log::error('Failed to upload profile photo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to upload profile photo',
                ErrorCodes::PROFILE_UPDATE_FAILED,
                null,
                500
            );
        }
    }

    /**
     * Delete profile photo
     */
    public function deleteProfilePhoto(): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = auth('api')->user();

            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    ErrorCodes::UNAUTHENTICATED,
                    null,
                    401
                );
            }

            if (!$user->profile_photo_path) {
                return ApiResponse::error(
                    'No profile photo to delete',
                    ErrorCodes::RESOURCE_NOT_FOUND,
                    null,
                    404
                );
            }

            // Delete photo from storage
            if (Storage::disk('public')->exists($user->profile_photo_path)) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            // Clear profile_photo_path
            $user->profile_photo_path = null;
            $user->save();

            // Log the deletion
            $this->activityLogService->logUserAction('profile_photo_deleted', $user);

            return ApiResponse::success([
                'message' => 'Profile photo deleted successfully',
                'user' => new UserResource($user),
            ], null, null, 200);

        } catch (\Exception $e) {
            Log::error('Failed to delete profile photo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to delete profile photo',
                ErrorCodes::PROFILE_UPDATE_FAILED,
                null,
                500
            );
        }
    }

    /**
     * Get tenant context for the user
     */
    protected function getTenantContext($user): ?array
    {
        $currentTenant = $user->getCurrentTenant();
        if (!$currentTenant) {
            return null;
        }

        $tenantUser = $user->tenants()
            ->where('tenants.id', $currentTenant->id)
            ->first();

        if (!$tenantUser) {
            return null;
        }

        return [
            'tenant_id' => $currentTenant->id,
            'role_id' => $tenantUser->pivot->role_id,
            'permissions' => json_decode($tenantUser->pivot->permissions, true) ?? [],
            'is_owner' => $tenantUser->pivot->role_id === 1,
            'custom_permissions' => [
                'granted' => [],
                'denied' => [],
            ],
        ];
    }
}
