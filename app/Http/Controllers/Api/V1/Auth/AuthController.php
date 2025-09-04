<?php

namespace App\Http\Controllers\API\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:users',
            'type' => 'required|in:email,phone',
            'role_id' => 'required|int|exists:roles,id',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
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
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        DB::beginTransaction();
        try {
            // Create the user first
            $user = User::create([
                'name' => $validatedData['name'],
                'identifier' => $validatedData['identifier'],
                'type' => $validatedData['type'],
                'password' => bcrypt($validatedData['password']),
                // 'verified_at' => now(), // Uncomment if you want to auto-verify
            ]);

            // Create the tenant (organization) with the user as owner
            $tenant = Tenant::create([
                'name' => $validatedData['organization_name'],
                'slug' => Str::slug($validatedData['organization_name']),
                'owner_id' => $user->id, // Set the owner_id to the newly created user
                'is_active' => true,
                'settings' => [
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                    'language' => 'en',
                    'features' => [],
                ],
                'created_by' => $user->id, // Set created_by to the user as well
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

            $token = auth('api')->login($user);

            DB::commit();

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
            DB::rollBack();
            return response()->json(['error' => 'Registration failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'identifier' => 'required|string',
                'password' => 'required|string',
                'type' => 'required|in:email,phone',
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
            });

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $credentials = $validator->validated();

            // Log para debug
            Log::info('Login attempt', ['identifier' => $credentials['identifier']]);

            if (! $token = auth('api')->attempt($credentials)) {
                Log::warning('Login failed - invalid credentials', ['identifier' => $credentials['identifier']]);
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            $user = auth('api')->user();
            Log::info('Login successful', ['user_id' => $user->id, 'identifier' => $user->identifier]);

            if ($user->must_change) {
                return response()->json([
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => 60 * 60 * 24, // 24 hours default
                    'must_change' => true
                ]);
            }

            return $this->respondWithToken($token);
        } catch (\Exception $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred during login. Please try again.'
            ], 500);
        }
    }

    public function me()
    {
        $user = auth('api')->user();

        // Try to get current tenant, but don't fail if none exists
        $currentTenant = null;
        $tenantContext = null;

        try {
            if ($user) {
                $currentTenant = $user->getCurrentTenant();
                if ($currentTenant) {
                    $tenantContext = $this->getTenantContext($user);
                }
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            Log::warning('Failed to get tenant context in me() method: ' . $e->getMessage());
        }

        return response()->json([
            'user' => $user,
            'current_tenant' => $currentTenant,
            'tenant_context' => $tenantContext,
        ]);
    }

    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        try {
            $token = auth('api')->refresh();
            return $this->respondWithToken($token);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token refresh failed'], 401);
        }
    }

    public function validateToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'access_token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Invalid request'], 422);
            }

            $token = $request->access_token;

            // Set the token for the current request
            auth('api')->setToken($token);

            // Check if the token is valid
            if (!auth('api')->check()) {
                return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
            }

            $user = auth('api')->user();

            // Check if user exists and is active
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 401);
            }

            // Check if user is active (using a simple check)
            if (isset($user->is_active) && !$user->is_active) {
                return response()->json(['success' => false, 'message' => 'User account is inactive'], 401);
            }

            // Generate a new token for security
            $newToken = auth('api')->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $newToken,
                    'user' => $user,
                    'current_tenant' => $user->getCurrentTenant(),
                    'tenant_context' => $this->getTenantContext($user),
                ],
                'message' => 'Token is valid'
            ]);

        } catch (\Exception $e) {
            Log::error('Token validation error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token validation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function respondWithToken($token)
    {
        $user = auth('api')->user();

        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 60 * 60 * 24, // 24 hours default
                'user' => $user,
                'current_tenant' => $user ? $user->getCurrentTenant() : null,
                'tenant_context' => $user ? $this->getTenantContext($user) : null,
            ],
            'message' => 'Login successful'
        ]);
    }

    protected function getTenantContext($user)
    {
        $currentTenant = $user->getCurrentTenant();
        if (!$currentTenant) {
            return null;
        }

        $tenantUser = $user->tenants()
            ->where('tenants.id', $currentTenant->id)
            ->first();

        return [
            'tenant_id' => $currentTenant->id,
            'role_id' => $tenantUser->pivot->role_id,
            'permissions' => json_decode($tenantUser->pivot->permissions, true) ?? [],
            'is_owner' => $tenantUser->pivot->role_id === 1, // Assuming role_id 1 is owner
            'custom_permissions' => [
                'granted' => [],
                'denied' => [],
            ],
        ];
    }
}
