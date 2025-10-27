<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\TenantInvitationResource;
use App\Http\Requests\CreateTenantRequest;
use App\Http\Requests\AddUserToTenantRequest;
use App\Models\Settings\Tenant;
use App\Models\User;
use App\Models\TenantInvitation;
use App\Services\TenantService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TenantController extends Controller
{
    public function __construct(
        private TenantService $tenantService,
        private NotificationService $notificationService
    ) {
        // $this->middleware('auth:api');
        $this->middleware('tenant')->except(['index', 'store', 'switch']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tenants = $user->activeTenants()
            ->withPivot(['role_id', 'current_tenant', 'status', 'joined_at'])
            ->when($request->get('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('tenants.name')
            ->get()
            ->map(function ($tenant) {
                $tenant->user_role = $tenant->pivot->role_id;
                $tenant->is_current = $tenant->pivot->current_tenant;
                $tenant->user_status = $tenant->pivot->status;
                $tenant->joined_at = $tenant->pivot->joined_at;
                return $tenant;
            });

        return TenantResource::collection($tenants);
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $tenant = $this->tenantService->createTenant($request->validated(), $user);

            return response()->json([
                'data' => new TenantResource($tenant),
                'message' => 'Tenant created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create tenant',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.view')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $tenantData = new TenantResource($tenant);
        $tenantData->additional([
            'user_context' => $user->getTenantContext(),
            'stats' => $this->tenantService->getTenantStats($tenant)
        ]);

        return response()->json(['data' => $tenantData]);
    }

    public function switch(Request $request): JsonResponse
    {
        $request->validate(['tenant_id' => 'required|integer|exists:tenants,id']);

        $user = $request->user();
        $tenantId = $request->get('tenant_id');

        try {
            $success = $this->tenantService->switchUserTenant($user, $tenantId);

            if (!$success) {
                return response()->json(['error' => 'Unable to switch to the requested tenant'], 403);
            }

            $newTenant = $user->getCurrentTenant();

            return response()->json([
                'data' => new TenantResource($newTenant),
                'user_context' => $user->getTenantContext(),
                'message' => "Switched to tenant: {$newTenant->name}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to switch tenant',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function invitations(Request $request): JsonResponse
    {
        $userLogged = Auth::user();
        $user = $request->user();
        $tenant = $userLogged->tenant_id;

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('users.view')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $invitations = TenantInvitation::where('tenant_id', $tenant->id)
            ->with(['inviter', 'tenant'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => TenantInvitationResource::collection($invitations),
            'message' => 'Invitations retrieved successfully'
        ]);
    }

    public function sendInvitation(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        // TEMPORARILY DISABLED: Permission check for invitation process
        // TODO: Re-enable after invitation flow is working properly
        /*
        if (!$user->hasTenantPermission('users.manage')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }
        */

        $request->validate([
            'identifier' => 'required|string',
            'type' => 'sometimes|in:email,phone',
            'role' => 'required|string',
            'message' => 'nullable|string|max:500',
        ]);

        $identifier = $request->get('identifier');
        $type = $request->get('type') ?? $this->detectIdentifierType($identifier);
        $role = $request->get('role');
        $message = $request->get('message');

        // Check if invitation already exists for this identifier and tenant
        $existingInvitation = TenantInvitation::where('tenant_id', $tenant->id)
            ->where('identifier', $identifier)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'error' => 'Invitation already exists',
                'message' => 'An invitation has already been sent to this user'
            ], 409);
        }

        // Check if user is already a member of this tenant
        $existingUser = User::where('identifier', $identifier)->first();
        if ($existingUser && $tenant->users()->where('user_id', $existingUser->id)->exists()) {
            return response()->json([
                'error' => 'User already member',
                'message' => 'This user is already a member of this tenant'
            ], 409);
        }

        try {
            // Create the invitation
            $invitation = TenantInvitation::create([
                'tenant_id' => $tenant->id,
                'identifier' => $identifier,
                'type' => $type,
                'role' => $role,
                'inviter_id' => $user->id,
                'message' => $message,
            ]);

            // Debug: Log invitation creation details
            Log::info('Invitation created successfully', [
                'invitation_id' => $invitation->id,
                'tenant_id' => $tenant->id,
                'identifier' => $identifier,
                'type' => $type,
                'role' => $role,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at,
                'environment' => app()->environment(),
                'app_url' => config('app.url'),
                'frontend_url' => config('app.frontend_url'),
                'env_frontend_url' => env('FRONTEND_URL')
            ]);

            // Debug: Test URL generation
            $acceptUrl = $invitation->getAcceptUrl();
            Log::info('Generated invitation URL', [
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'accept_url' => $acceptUrl,
                'url_length' => strlen($acceptUrl),
                'can_be_accepted' => $invitation->canBeAccepted(),
                'is_pending' => $invitation->isPending(),
                'is_expired' => $invitation->isExpired()
            ]);

            // Send notification
            $this->notificationService->sendInvitation($invitation, $tenant, $user);

            return response()->json([
                'data' => new TenantInvitationResource($invitation),
                'message' => 'Invitation sent successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to send invitation', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'identifier' => $identifier,
                'inviter_id' => $user->id
            ]);

            return response()->json([
                'error' => 'Failed to send invitation',
                'message' => 'An error occurred while sending the invitation'
            ], 500);
        }
    }

    public function cancelInvitation(Request $request, $invitationId): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        // TEMPORARILY DISABLED: Permission check for invitation management
        // TODO: Re-enable after invitation flow is working properly
        /*
        if (!$user->hasTenantPermission('users.manage')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }
        */

        try {
            $invitation = TenantInvitation::where('id', $invitationId)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$invitation) {
                return response()->json([
                    'error' => 'Invitation not found',
                    'message' => 'The specified invitation does not exist'
                ], 404);
            }

            if ($invitation->status !== 'pending') {
                return response()->json([
                    'error' => 'Invalid invitation status',
                    'message' => 'Only pending invitations can be cancelled'
                ], 400);
            }

            $invitation->markAsCancelled();

            return response()->json([
                'message' => 'Invitation cancelled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel invitation', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitationId,
                'tenant_id' => $tenant->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'error' => 'Failed to cancel invitation',
                'message' => 'An error occurred while cancelling the invitation'
            ], 500);
        }
    }

    public function deleteInvitation(Request $request, $invitationId): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        // TEMPORARILY DISABLED: Permission check for invitation management
        // TODO: Re-enable after invitation flow is working properly
        /*
        if (!$user->hasTenantPermission('users.manage')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }
        */

        try {
            $invitation = TenantInvitation::where('id', $invitationId)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$invitation) {
                return response()->json([
                    'error' => 'Invitation not found',
                    'message' => 'The specified invitation does not exist'
                ], 404);
            }

            if (!in_array($invitation->status, ['cancelled', 'expired'])) {
                return response()->json([
                    'error' => 'Invalid invitation status',
                    'message' => 'Only cancelled or expired invitations can be permanently deleted'
                ], 400);
            }

            // Permanently delete the invitation
            $invitation->delete();

            return response()->json([
                'message' => 'Invitation permanently deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to permanently delete invitation', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitationId,
                'tenant_id' => $tenant->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'error' => 'Failed to permanently delete invitation',
                'message' => 'An error occurred while deleting the invitation'
            ], 500);
        }
    }

    /**
     * Detect if the identifier is an email or phone number
     */
    private function detectIdentifierType(string $identifier): string
    {
        // Email regex pattern
        $emailPattern = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';

        // Phone regex pattern (supports international format and common formats)
        $phonePattern = '/^[\+]?[1-9][\d\s\-\(\)]{7,15}$/';

        // Clean the identifier (remove spaces, dashes, parentheses) for validation
        $cleanIdentifier = preg_replace('/[\s\-\(\)]/', '', $identifier);

        if (preg_match($emailPattern, $identifier)) {
            return 'email';
        } elseif (preg_match($phonePattern, $identifier) && strlen($cleanIdentifier) >= 8 && strlen($cleanIdentifier) <= 15) {
            return 'phone';
        } else {
            // Default to email if we can't determine
            return 'email';
        }
    }

    /**
     * Get tenant settings
     */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.view')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $settings = $tenant->settings ?? [];

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'settings' => $settings
            ]
        ]);
    }

    /**
     * Update tenant settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.manage_settings')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'settings' => 'required|array',
        ]);

        try {
            $updatedTenant = $this->tenantService->updateTenantSettings($tenant, $request->get('settings'));

            return response()->json([
                'data' => new TenantResource($updatedTenant),
                'message' => 'Tenant settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update tenant settings',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get tenant branding configuration
     */
    public function branding(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        $settings = $tenant->settings ?? [];

        // Ensure settings is an array
        if (!is_array($settings)) {
            $settings = [];
        }

        $branding = $settings['branding'] ?? [];

        // Ensure branding is an array
        if (!is_array($branding)) {
            $branding = [];
        }

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'branding' => $branding
            ]
        ]);
    }

    /**
     * Update tenant branding configuration
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('tenants.manage_settings')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'branding' => 'required|array',
            'branding.logo' => 'sometimes|string',
            'branding.favicon' => 'sometimes|string',
            'branding.primaryColor' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'branding.secondaryColor' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'branding.accentColor' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'branding.companyName' => 'sometimes|string|max:255',
            'branding.shortName' => 'sometimes|string|max:100',
            'branding.description' => 'sometimes|string|max:500',
            'branding.website' => 'sometimes|string',
            'branding.email' => 'sometimes|email',
            'branding.phone' => 'sometimes|string|max:20',
            'branding.address' => 'sometimes|string|max:500',
        ]);

        try {
            $currentSettings = $tenant->settings ?? [];

            // Ensure currentSettings is an array
            if (!is_array($currentSettings)) {
                $currentSettings = [];
            }

            // Ensure branding is an array before merging
            $currentBranding = $currentSettings['branding'] ?? [];
            if (!is_array($currentBranding)) {
                $currentBranding = [];
            }

            $newBranding = $request->get('branding');
            if (!is_array($newBranding)) {
                $newBranding = [];
            }

            $currentSettings['branding'] = array_merge($currentBranding, $newBranding);

            $updatedTenant = $this->tenantService->updateTenantSettings($tenant, $currentSettings);

            return response()->json([
                'data' => new TenantResource($updatedTenant),
                'message' => 'Tenant branding updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update tenant branding', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'payload' => $request->get('branding'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to update tenant branding',
                'message' => $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'payload' => $request->get('branding')
                ] : null
            ], 422);
        }
    }

    /**
     * Get tenant users
     */
    public function users(Request $request)
    {
        $user = $request->user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        if (!$user->hasTenantPermission('users.view')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $users = $this->tenantService->getTenantUsers($tenant, $request->get('status', 'active'));

        return UserResource::collection($users);
    }

    /**
     * Add user to tenant
     */
    public function addUser(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        $request->validate([
            'identifier' => 'required|string',
            'type' => 'sometimes|in:email,phone',
            'role' => 'required|string',
        ]);

        $identifier = $request->get('identifier');
        $type = $request->get('type') ?? $this->detectIdentifierType($identifier);
        $role = $request->get('role');

        // Try to find the user by identifier
        $targetUser = \App\Models\User::where('identifier', $identifier)->first();

        if ($targetUser) {
            // User exists, add to tenant
            try {
                app(\App\Services\TenantService::class)->addUserToTenant(
                    $tenant,
                    $targetUser,
                    $role,
                    false,
                    []
                );

                return response()->json([
                    'data' => new \App\Http\Resources\UserResource($targetUser),
                    'message' => 'User added to tenant successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to add user to tenant',
                    'message' => $e->getMessage()
                ], 422);
            }
        } else {
            // User does not exist, return error for now
            // TODO: Implement invitation system when TenantInvitation model is available
            return response()->json([
                'error' => 'User not found',
                'message' => 'The user with this identifier does not exist. Please ensure the user is registered in the system.'
            ], 404);
        }
    }

    /**
     * Remove user from tenant
     */
    public function removeUser(Request $request, $userId): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        // Check if user has permission to remove users
        if (!$currentUser->hasTenantPermission('users.manage')) {
            return response()->json(['error' => 'Insufficient permissions to remove users'], 403);
        }

        try {
            $targetUser = \App\Models\User::findOrFail($userId);

            // Prevent users from removing themselves
            if ($currentUser->id === $targetUser->id) {
                return response()->json(['error' => 'You cannot remove yourself from the tenant'], 422);
            }

            // Check if target user is actually in this tenant
            $userInTenant = $tenant->users()->where('user_id', $userId)->exists();
            if (!$userInTenant) {
                return response()->json(['error' => 'User is not a member of this tenant'], 404);
            }

            // Check if target user is the owner (prevent removing owner)
            $targetUserContext = $targetUser->getTenantContext($tenant->id);
            $isOwner = $targetUserContext && $targetUserContext['is_owner'];

            // Also check by role name for additional safety
            if (!$isOwner && $targetUserContext && $targetUserContext['role']) {
                $isOwner = in_array(strtolower($targetUserContext['role']), ['owner', 'proprietário da organização']);
            }

            if ($isOwner) {
                return response()->json(['error' => 'Cannot remove the tenant owner'], 422);
            }

            // Log the removal action for audit purposes
            \Log::info('User removed from tenant', [
                'removed_user_id' => $userId,
                'removed_user_name' => $targetUser->name,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'removed_by_user_id' => $currentUser->id,
                'removed_by_user_name' => $currentUser->name,
            ]);

            // Remove user from tenant
            $tenant->users()->detach($userId);

            // Delete any pending invitations for this user in this tenant
            // Check for both email and phone identifiers since the system supports both
            $deletedInvitations = TenantInvitation::where('tenant_id', $tenant->id)
                ->where(function ($query) use ($targetUser) {
                    $query->where('identifier', $targetUser->email)
                          ->orWhere('identifier', $targetUser->identifier);
                })
                ->where('status', 'pending')
                ->delete();

            // Log invitation deletion if any were found
            if ($deletedInvitations > 0) {
                \Log::info('Pending invitations deleted for removed user', [
                    'user_id' => $userId,
                    'user_email' => $targetUser->email,
                    'tenant_id' => $tenant->id,
                    'deleted_invitations_count' => $deletedInvitations,
                ]);
            }

            return response()->json([
                'message' => 'User removed from tenant successfully' . ($deletedInvitations > 0 ? ' and pending invitations deleted' : ''),
                'deleted_invitations_count' => $deletedInvitations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove user from tenant',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update user role in tenant
     */
    public function updateUserRole(Request $request, $userId): JsonResponse
    {
        $currentUser = $request->user();
        $tenant = $currentUser->getCurrentTenant();

        if (!$tenant) {
            return response()->json(['error' => 'No current tenant set'], 404);
        }

        $request->validate([
            'role' => 'required|string',
        ]);

        try {
            $targetUser = \App\Models\User::findOrFail($userId);

            // Update user role in tenant
            $tenant->users()->updateExistingPivot($userId, [
                'role_id' => $request->get('role')
            ]);

            return response()->json([
                'message' => 'User role updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update user role',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Validate invitation token (public endpoint)
     */
    public function validateInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $token = $request->input('token');

        try {
            $invitation = TenantInvitation::where('token', $token)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->with(['tenant', 'inviter'])
                ->first();

            if (!$invitation) {
                return response()->json([
                    'error' => 'Invalid or expired invitation',
                    'message' => 'This invitation is invalid, expired, or has already been used.'
                ], 404);
            }

            return response()->json([
                'data' => [
                    'invitation' => new TenantInvitationResource($invitation),
                    'tenant' => [
                        'id' => $invitation->tenant->id,
                        'name' => $invitation->tenant->name,
                        'description' => $invitation->tenant->description
                    ],
                    'inviter' => [
                        'id' => $invitation->inviter->id,
                        'name' => $invitation->inviter->name ?? $invitation->inviter->first_name . ' ' . $invitation->inviter->last_name
                    ],
                    'role' => $invitation->role,
                    'expires_at' => $invitation->expires_at->toISOString()
                ],
                'message' => 'Invitation is valid'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to validate invitation', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to validate invitation',
                'message' => 'An error occurred while validating the invitation'
            ], 500);
        }
    }

    /**
     * Debug invitation link generation (for testing purposes)
     */
    public function debugInvitationLink(Request $request): JsonResponse
    {
        $request->validate([
            'invitation_id' => 'required|integer|exists:tenant_invitations,id'
        ]);

        $invitationId = $request->input('invitation_id');

        try {
            $invitation = TenantInvitation::findOrFail($invitationId);

            // Generate the URL and validate format
            $acceptUrl = $invitation->getAcceptUrl();
            $urlValidation = $invitation->validateUrlFormat();

            // Get all relevant configuration
            $config = [
                'environment' => app()->environment(),
                'app_url' => config('app.url'),
                'frontend_url_config' => config('app.frontend_url'),
                'env_frontend_url' => env('FRONTEND_URL'),
                'app_env' => env('APP_ENV'),
                'app_debug' => env('APP_DEBUG')
            ];

            // Get invitation details
            $invitationDetails = [
                'id' => $invitation->id,
                'tenant_id' => $invitation->tenant_id,
                'identifier' => $invitation->identifier,
                'type' => $invitation->type,
                'role' => $invitation->role,
                'token' => $invitation->token,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at,
                'can_be_accepted' => $invitation->canBeAccepted(),
                'is_pending' => $invitation->isPending(),
                'is_expired' => $invitation->isExpired()
            ];

            return response()->json([
                'data' => [
                    'invitation' => $invitationDetails,
                    'configuration' => $config,
                    'generated_url' => $acceptUrl,
                    'url_validation' => $urlValidation
                ],
                'message' => 'Invitation link debug information'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to debug invitation link', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to debug invitation link',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept invitation (public endpoint)
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => 'required|string|min:8'
        ]);

        $token = $request->input('token');

        // Debug: Log the acceptance attempt
        Log::info('Invitation acceptance attempt', [
            'token' => $token,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'request_data' => $request->all()
        ]);

        try {
            $invitation = TenantInvitation::where('token', $token)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->with(['tenant', 'inviter'])
                ->first();

            if (!$invitation) {
                return response()->json([
                    'error' => 'Invalid or expired invitation',
                    'message' => 'This invitation is invalid, expired, or has already been used.'
                ], 404);
            }

            // Check if user already exists with this identifier
            $existingUser = User::where('identifier', $invitation->identifier)->first();
            if ($existingUser) {
                return response()->json([
                    'error' => 'User already exists',
                    'message' => 'A user with this email address already exists in the system.'
                ], 422);
            }

            // Create new user
            $user = User::create([
                'name' => $request->input('first_name') . ' ' . $request->input('last_name'),
                'identifier' => $invitation->identifier,
                'type' => 'email',
                'password' => bcrypt($request->input('password')),
                'tenant_id' => $invitation->tenant_id,
                'verified_at' => now() // Auto-verify since they came through invitation
            ]);

            // Get role ID by name
            $role = Role::where('name', $invitation->role)->first();

            // Debug: Log role resolution
            Log::info('Role resolution for invitation', [
                'invitation_role' => $invitation->role,
                'role_found' => $role ? true : false,
                'role_id' => $role ? $role->id : null,
                'role_name' => $role ? $role->name : null
            ]);

            if (!$role) {
                throw new \Exception("Role '{$invitation->role}' not found");
            }

            // Add user to tenant with the specified role
            $invitation->tenant->users()->attach($user->id, [
                'role_id' => $role->id,
                'status' => 'active',
                'current_tenant' => true, // Set as current tenant
                'joined_at' => now()
            ]);

            // Debug: Log role assignment
            Log::info('Assigning role to user', [
                'user_id' => $user->id,
                'role_name' => $invitation->role,
                'role_id' => $role->id,
                'tenant_id' => $invitation->tenant_id
            ]);

            // Assign role to user with tenant context using direct database insertion
            DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_type' => User::class,
                'model_id' => $user->id,
                'tenant_id' => $invitation->tenant_id
            ]);

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            // Log the acceptance
            Log::info('Invitation accepted', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'tenant_id' => $invitation->tenant_id,
                'role' => $invitation->role
            ]);

            return response()->json([
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'identifier' => $user->identifier
                    ],
                    'tenant' => [
                        'id' => $invitation->tenant->id,
                        'name' => $invitation->tenant->name
                    ],
                    'role' => $invitation->role
                ],
                'message' => 'Invitation accepted successfully. You can now sign in to your account.'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to accept invitation', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to accept invitation',
                'message' => 'An error occurred while accepting the invitation. Please try again.'
            ], 500);
        }
    }
}
