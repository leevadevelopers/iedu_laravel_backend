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
            'identifier' => 'required|string|max:255|unique:users,identifier,' . $user->id,
            'type' => 'required|in:email,phone',
            'phone' => 'nullable|string|max:20',
            'whatsapp_phone' => 'nullable|string|max:20',
            'user_type' => 'nullable|in:student,parent,teacher,admin,staff,principal,counselor,nurse',
            'emergency_contact_json' => 'nullable|json',
            'transport_notification_preferences' => 'nullable|json',
            'settings' => 'nullable|json',
            'is_active' => 'nullable|boolean',
            'must_change' => 'nullable|boolean',
        ]);

        // Validação condicional baseada no tipo de identifier
        $validator->after(function ($validator) use ($request) {
            $type = $request->input('type');
            $identifier = $request->input('identifier');

            if ($type === 'email') {
                if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('identifier', 'O campo identifier deve ser um email válido quando o tipo for email.');
                }

                // Validação adicional para formato de email
                if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $identifier)) {
                    $validator->errors()->add('identifier', 'Formato de email inválido.');
                }

                // Verificar se não contém caracteres perigosos
                if (preg_match('/[<>"\']/', $identifier)) {
                    $validator->errors()->add('identifier', 'O email não pode conter caracteres especiais como < > " \'.');
                }
            } elseif ($type === 'phone') {
                // Remover todos os caracteres não numéricos para validação
                $cleanPhone = preg_replace('/[^0-9]/', '', $identifier);

                // Validar se contém apenas números após limpeza
                if (!preg_match('/^[0-9]+$/', $cleanPhone)) {
                    $validator->errors()->add('identifier', 'O telefone deve conter apenas números e caracteres de formatação válidos.');
                }

                // Validar comprimento do telefone (8-15 dígitos é um padrão internacional)
                if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15) {
                    $validator->errors()->add('identifier', 'O telefone deve ter entre 8 e 15 dígitos.');
                }

                // Validar formato brasileiro se começar com +55 ou 55
                if (preg_match('/^(\+?55|55)/', $cleanPhone)) {
                    if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 13) {
                        $validator->errors()->add('identifier', 'Telefone brasileiro deve ter entre 10 e 13 dígitos (incluindo DDD).');
                    }
                }

                // Validar se não é apenas zeros ou números repetidos
                if (preg_match('/^(.)\1+$/', $cleanPhone)) {
                    $validator->errors()->add('identifier', 'O telefone não pode conter apenas números repetidos.');
                }
            }

            // Validação para campos de telefone adicionais
            if ($request->has('phone') && $request->phone) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone);
                if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15) {
                    $validator->errors()->add('phone', 'O telefone deve ter entre 8 e 15 dígitos.');
                }
            }

            if ($request->has('whatsapp_phone') && $request->whatsapp_phone) {
                $cleanWhatsApp = preg_replace('/[^0-9]/', '', $request->whatsapp_phone);
                if (strlen($cleanWhatsApp) < 8 || strlen($cleanWhatsApp) > 15) {
                    $validator->errors()->add('whatsapp_phone', 'O WhatsApp deve ter entre 8 e 15 dígitos.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare update data
            $updateData = [
                'name' => $request->name,
                'identifier' => $request->identifier,
                'type' => $request->type,
            ];

            // Add optional fields if provided
            if ($request->has('phone')) {
                $updateData['phone'] = $request->phone;
            }
            if ($request->has('whatsapp_phone')) {
                $updateData['whatsapp_phone'] = $request->whatsapp_phone;
            }
            if ($request->has('user_type')) {
                $updateData['user_type'] = $request->user_type;
            }
            if ($request->has('emergency_contact_json')) {
                $updateData['emergency_contact_json'] = $request->emergency_contact_json;
            }
            if ($request->has('transport_notification_preferences')) {
                $updateData['transport_notification_preferences'] = $request->transport_notification_preferences;
            }
            if ($request->has('settings')) {
                $updateData['settings'] = $request->settings;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            if ($request->has('must_change')) {
                $updateData['must_change'] = $request->must_change;
            }

            // Update user profile
            $user->update($updateData);

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
     * Update specific user fields (without changing identifier)
     */
    public function updateSpecificFields(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validate the request - campos que podem ser atualizados sem afetar identifier
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp_phone' => 'nullable|string|max:20',
            'user_type' => 'nullable|in:student,parent,teacher,admin,staff,principal,counselor,nurse',
            'emergency_contact_json' => 'nullable|json',
            'transport_notification_preferences' => 'nullable|json',
            'settings' => 'nullable|json',
            'is_active' => 'nullable|boolean',
            'must_change' => 'nullable|boolean',
        ]);

        // Validação para campos de telefone
        $validator->after(function ($validator) use ($request) {
            if ($request->has('phone') && $request->phone) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone);
                if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15) {
                    $validator->errors()->add('phone', 'O telefone deve ter entre 8 e 15 dígitos.');
                }
            }

            if ($request->has('whatsapp_phone') && $request->whatsapp_phone) {
                $cleanWhatsApp = preg_replace('/[^0-9]/', '', $request->whatsapp_phone);
                if (strlen($cleanWhatsApp) < 8 || strlen($cleanWhatsApp) > 15) {
                    $validator->errors()->add('whatsapp_phone', 'O WhatsApp deve ter entre 8 e 15 dígitos.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare update data - apenas campos permitidos
            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('phone')) {
                $updateData['phone'] = $request->phone;
            }
            if ($request->has('whatsapp_phone')) {
                $updateData['whatsapp_phone'] = $request->whatsapp_phone;
            }
            if ($request->has('user_type')) {
                $updateData['user_type'] = $request->user_type;
            }
            if ($request->has('emergency_contact_json')) {
                $updateData['emergency_contact_json'] = $request->emergency_contact_json;
            }
            if ($request->has('transport_notification_preferences')) {
                $updateData['transport_notification_preferences'] = $request->transport_notification_preferences;
            }
            if ($request->has('settings')) {
                $updateData['settings'] = $request->settings;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            if ($request->has('must_change')) {
                $updateData['must_change'] = $request->must_change;
            }

            // Update user profile
            $user->update($updateData);

            // Refresh user data
            $user->refresh();

            return response()->json([
                'message' => 'Profile fields updated successfully',
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile fields',
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
