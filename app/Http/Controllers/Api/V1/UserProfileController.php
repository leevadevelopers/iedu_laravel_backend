<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('tenant');
    }

    /**
     * Update the authenticated user's profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update user profile
            $user->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'company' => $request->company,
                'job_title' => $request->job_title,
                'bio' => $request->bio,
            ]);

            // Refresh user data
            $user->refresh();

            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Delete old avatar if exists
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            
            // Update user profile photo path
            $user->update([
                'profile_photo_path' => $avatarPath
            ]);

            // Refresh user data
            $user->refresh();

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'profile_photo_path' => $avatarPath,
                    'avatar_url' => asset('storage/' . $avatarPath)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the authenticated user's profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Switch user's current tenant
     */
    public function switchTenant(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tenantId = $request->tenant_id;
            
            // Check if user belongs to this tenant
            if (!$user->belongsToTenant($tenantId)) {
                return response()->json([
                    'message' => 'User does not belong to this tenant'
                ], 403);
            }

            // Switch tenant
            $success = $user->switchTenant($tenantId);
            
            if ($success) {
                $tenant = $user->getCurrentTenant();
                return response()->json([
                    'message' => 'Tenant switched successfully',
                    'data' => $tenant
                ]);
            } else {
                return response()->json([
                    'message' => 'Failed to switch tenant'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to switch tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's tenants
     */
    public function getUserTenants(Request $request): JsonResponse
    {
        $user = $request->user();
        
        try {
            $tenants = $user->tenants()->where('status', 'active')->get();
            
            return response()->json([
                'data' => $tenants
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get user tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
