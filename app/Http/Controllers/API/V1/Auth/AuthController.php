<?php

namespace App\Http\Controllers\API\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;
use App\Http\Resources\TenantResource;
use App\Events\TenantCreated;
use App\Services\Auth\SignupIdempotencyService;
use App\Services\Auth\SchoolRegistrationService;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{

    public function __construct(
        private ActivityLogService $activityLogService,
        private SignupIdempotencyService $signupIdempotencyService,
        private SchoolRegistrationService $schoolRegistrationService
    ) {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function register(Request $request)
    {
        $schoolOwnerRole = Role::where('name', 'school_owner')->first();
        if (!$schoolOwnerRole) {
            return response()->json(['error' => 'school_owner role is not configured'], 500);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $invalidKeyResponse = $this->signupIdempotencyService->validateKeyFormat($idempotencyKey);
            if ($invalidKeyResponse) {
                return $invalidKeyResponse;
            }

            $payloadHash = $this->signupIdempotencyService->hashSignupPayload($request);

            return Cache::lock($this->signupIdempotencyService->lockKey($idempotencyKey), 30)->block(10, function () use ($request, $schoolOwnerRole, $idempotencyKey, $payloadHash) {
                $cached = $this->signupIdempotencyService->getCachedEntry($idempotencyKey);
                if ($cached) {
                    if ($cached['payload_hash'] === $payloadHash) {
                        $decoded = json_decode($cached['body'], true);

                        return response()->json($decoded, $cached['status']);
                    }

                    return response()->json([
                        'error' => 'idempotency_conflict',
                        'message' => 'Idempotency-Key was already used with a different request body.',
                    ], 409);
                }

                $response = $this->executeSchoolRegistration($request, $schoolOwnerRole);
                if ($response->getStatusCode() === 200) {
                    $this->signupIdempotencyService->rememberSuccess($idempotencyKey, $payloadHash, $response);
                }

                return $response;
            });
        }

        return $this->executeSchoolRegistration($request, $schoolOwnerRole);
    }

    private function executeSchoolRegistration(Request $request, Role $schoolOwnerRole): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:users',
            'type' => 'required|in:email,phone',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => [
                'required_without:school_name',
                'nullable',
                'string',
                'max:255',
            ],
            'school_name' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|max:2',
            'timezone' => 'nullable|string|max:100',
            'locale' => 'nullable|string|max:10',
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
        $organizationName = trim((string)($validatedData['organization_name'] ?? $validatedData['school_name'] ?? ''));
        if ($organizationName === '') {
            return response()->json([
                'errors' => [
                    'organization_name' => ['O nome da escola é obrigatório.']
                ]
            ], 422);
        }

        try {
            $createdData = $this->schoolRegistrationService->provisionSchoolOwner(
                $validatedData,
                $organizationName,
                $schoolOwnerRole
            );

            $user = $createdData['user'];
            $tenant = $createdData['tenant'];

            session(['tenant_id' => $tenant->id]);
            $user->refresh();

            $token = auth('api')->login($user);
            $tenantContext = $this->getTenantContext($user);
            event(new TenantCreated($tenant, $user, [
                'source' => 'public_signup',
                'country_code' => $validatedData['country_code'] ?? 'MZ',
                'locale' => $validatedData['locale'] ?? 'pt-MZ',
            ]));

            $userPayload = (new UserResource($user))->resolve();
            $userPayload['current_tenant_context'] = $tenantContext;

            // Send welcome email
            try {
                app(\App\Services\Email\EmailService::class)->sendUserWelcomeEmail($user);
            } catch (\Exception $e) {
                \Log::warning('Failed to send welcome email to user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => $userPayload,
                'current_tenant' => new TenantResource($tenant),
                'tenant_context' => $tenantContext,
                'message' => 'Registration successful',
            ]);
        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Registration failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required',
            'password' => 'required|string',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ]);

        $identifier = $request->get('identifier');
        $password = $request->get('password');

        // Buscar usuário por identifier (email ou telefone)
        $user = \App\Models\User::where('identifier', $identifier)->first();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            $this->activityLogService->logSecurityEvent('failed_login_attempt', [
                'identifier' => $identifier,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'identifier' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->isActive()) {
            return response()->json([
                'error' => 'Account is inactive. Please contact administrator.'
            ], 403);
        }

        // Autenticar e gerar token JWT Tymon\JWTAuth\Facades\JWTAuth
        $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

        // Check if user is super admin (cross-tenant role)
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        // Handle tenant context (skip for super_admin)
        if (!$isSuperAdmin) {
        if ($request->has('tenant_id') && $request->get('tenant_id') !== null) {
            $tenantId = (int) $request->get('tenant_id');
            if (!$user->belongsToTenant($tenantId)) {
                return response()->json([
                    'error' => 'You do not have access to the requested organization.'
                ], 403);
            }
            $user->switchTenant($tenantId);
        } else {
            // Set to first available tenant
            $firstTenant = $user->activeTenants()->first();
            if ($firstTenant) {
                session(['tenant_id' => $firstTenant->id]);
                }
            }
        }

        $user->updateLastLogin();

        $this->activityLogService->logUserAction('user_logged_in', $user);

        // Get tenant context (handles super_admin case)
        $currentTenant = $user->getCurrentTenant();
        $tenantContext = null;

        // Check if user is super admin (cross-tenant role)
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        
        if ($isSuperAdmin) {
            // If super admin, create a special tenant context
            $tenantContext = [
                'tenant_id' => null,
                'role' => 'super_admin',
                'permissions' => ['*'], // Super admin has all permissions
                'is_owner' => false,
                'custom_permissions' => [
                    'granted' => [],
                    'denied' => [],
                ],
            ];
        } elseif ($currentTenant) {
            $tenantContext = $this->getTenantContext($user);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => \Tymon\JWTAuth\Facades\JWTAuth::factory()->getTTL() * 60,
            'user' => new UserResource($user),
            'current_tenant' => $currentTenant ? new TenantResource($currentTenant) : null,
            'tenant_context' => $tenantContext,
        ]);
    }
    public function me()
    {
        $user = auth('api')->user();

        // Try to get current tenant, but don't fail if none exists
        $currentTenant = null;
        $tenantContext = null;
        $currentSchool = null;

        try {
            if ($user) {
                // Check if user is super admin (cross-tenant role)
                $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
                
                // If super admin, create a special tenant context
                if ($isSuperAdmin) {
                    $tenantContext = [
                        'tenant_id' => null,
                        'role' => 'super_admin',
                        'permissions' => ['*'], // Super admin has all permissions
                        'is_owner' => false,
                        'custom_permissions' => [
                            'granted' => [],
                            'denied' => [],
                        ],
                    ];
                } else {
                    $currentTenant = $user->getCurrentTenant();
                    if ($currentTenant) {
                        $tenantContext = $this->getTenantContext($user);
                    }
                    
                    // Get current school for the user (from session or first active school)
                    try {
                        $currentSchool = $user->getCurrentSchool();
                    } catch (\Exception $e) {
                        // Log but don't fail if school retrieval fails
                        Log::warning('Failed to get current school in me() method: ' . $e->getMessage());
                    }
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
            'current_school' => $currentSchool ? [
                'id' => $currentSchool->id,
                'name' => $currentSchool->display_name,
                'school_code' => $currentSchool->school_code,
            ] : null,
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
            $user = auth('api')->user();
            
            // Return format matching frontend expectations
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ]);
        } catch (\Exception $e) {
            \Log::error('Token refresh failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
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

        // Check if user is super admin and create tenant context if needed
        $tenantContext = null;
        if ($user) {
            $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
            
            if ($isSuperAdmin) {
                $tenantContext = [
                    'tenant_id' => null,
                    'role' => 'super_admin',
                    'permissions' => ['*'], // Super admin has all permissions
                    'is_owner' => false,
                    'custom_permissions' => [
                        'granted' => [],
                        'denied' => [],
                    ],
                ];
            } else {
                $tenantContext = $this->getTenantContext($user);
            }
        }

        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 60 * 60 * 24, // 24 hours default
                'user' => $user,
                'current_tenant' => $user ? $user->getCurrentTenant() : null,
                'tenant_context' => $tenantContext,
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

        // Get role name from role_id
        $role = null;
        $isOwner = false;
        if ($tenantUser && $tenantUser->pivot->role_id) {
            $roleModel = Role::find($tenantUser->pivot->role_id);
            $role = $roleModel ? $roleModel->name : null;
            $isOwner = $role === 'school_owner';
        }

        return [
            'tenant_id' => (string) $currentTenant->id,
            'role' => $role,
            'role_id' => $tenantUser->pivot->role_id ?? null,
            'permissions' => json_decode($tenantUser->pivot->permissions, true) ?? [],
            'is_owner' => $isOwner,
            'custom_permissions' => [
                'granted' => [],
                'denied' => [],
            ],
        ];
    }
}
