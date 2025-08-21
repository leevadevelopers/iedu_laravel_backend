<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Models\Settings\Tenant;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:users',
            'type' => 'required|in:email,phone',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        \DB::beginTransaction();
        try {
            // Create the tenant (organization)
            $tenant = Tenant::create([
                'name' => $validatedData['organization_name'],
                'slug' => \Str::slug($validatedData['organization_name']),
                'is_active' => true,
                'settings' => [
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                    'language' => 'en',
                    'features' => [],
                ],
                'created_by' => null, // will update after user creation
            ]);

            // Create the user
            $user = User::create([
                'name' => $validatedData['name'],
                'identifier' => $validatedData['identifier'],
                'type' => $validatedData['type'],
                'password' => bcrypt($validatedData['password']),
                // 'verified_at' => now(), // Uncomment if you want to auto-verify
            ]);

            // Attach user to tenant as owner
            $ownerRoleId = Role::where('name', 'owner')->value('id');
            $user->tenants()->attach($tenant->id, [
                'role_id' => $ownerRoleId,
                'permissions' => json_encode(['tenants.', 'users.', 'projects.', 'finance.']),
                'current_tenant' => true,
                'joined_at' => now(),
                'status' => 'active',
            ]);

            // Update tenant's created_by
            $tenant->update(['created_by' => $user->id]);

            $token = auth('api')->login($user);

            \DB::commit();

            // Prepare response
            $userData = $user->toArray();
            $userData['current_tenant_context'] = [
                'tenant_id' => $tenant->id,
                'role' => 'owner',
                'permissions' => ['tenants.', 'users.', 'projects.', 'finance.'],
                'is_owner' => true,
                'custom_permissions' => [
                    'granted' => [],
                    'denied' => [],
                ],
            ];

            $tenantData = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'is_active' => $tenant->is_active,
                'settings' => $tenant->settings,
                'features' => $tenant->getFeatures(),
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ];

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => $userData,
                'current_tenant' => $tenantData,
                'message' => 'Registration successful',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Registration failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = $validator->validated();

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth('api')->user();
        
        if ($user->must_change) {
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'must_change' => true
            ]);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        $user = auth('api')->user();
        return response()->json([
            'user' => $user,
            'current_tenant' => $user->getCurrentTenant(),
            'tenant_context' => $user->getTenantContext(),
        ]);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
