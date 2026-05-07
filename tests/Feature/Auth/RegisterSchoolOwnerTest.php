<?php

namespace Tests\Feature\Auth;

use App\Models\Settings\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterSchoolOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function ensureSchoolOwnerRole(): Role
    {
        return Role::query()->updateOrCreate(
            ['name' => 'school_owner', 'guard_name' => 'api'],
            [
                'display_name' => 'Dono da Escola',
            ]
        );
    }

    private function validPayload(string $email, string $org = 'Escola Teste'): array
    {
        return [
            'name' => 'Owner User',
            'identifier' => $email,
            'type' => 'email',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'organization_name' => $org,
            'country_code' => 'MZ',
            'timezone' => 'Africa/Maputo',
            'locale' => 'pt-MZ',
        ];
    }

    public function test_happy_path_creates_user_tenant_tenant_users_and_school_owner_context(): void
    {
        $this->ensureSchoolOwnerRole();
        $email = 'owner-'.uniqid().'@example.com';

        $response = $this->postJson('/api/v1/auth/sign-up', $this->validPayload($email));

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('access_token', $data);
        $this->assertSame('school_owner', $data['tenant_context']['role']);
        $this->assertTrue($data['tenant_context']['is_owner']);
        $this->assertNotEmpty($data['tenant_context']['tenant_id']);
        $tenant = Tenant::query()->first();
        $this->assertNotNull($tenant);
        $this->assertSame((string) $tenant->id, (string) $data['tenant_context']['tenant_id']);

        $this->assertDatabaseHas('users', ['identifier' => $email]);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Tenant::query()->count());

        $user = User::query()->where('identifier', $email)->first();
        $role = Role::query()->where('name', 'school_owner')->where('guard_name', 'api')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($tenant);
        $this->assertNotNull($role);
        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $user->id,
            'model_type' => User::class,
            'role_id' => $role->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_idempotency_same_key_and_payload_replays_without_duplicating_rows(): void
    {
        $this->ensureSchoolOwnerRole();
        $email = 'idemp-'.uniqid().'@example.com';
        $key = (string) \Illuminate\Support\Str::uuid();
        $payload = $this->validPayload($email);

        $r1 = $this->postJson('/api/v1/auth/sign-up', $payload, ['Idempotency-Key' => $key]);
        $r1->assertOk();
        $token1 = $r1->json('access_token');

        $r2 = $this->postJson('/api/v1/auth/sign-up', $payload, ['Idempotency-Key' => $key]);
        $r2->assertOk();
        $this->assertSame($r1->json(), $r2->json());
        $this->assertSame($token1, $r2->json('access_token'));

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_idempotency_same_key_different_payload_returns_409(): void
    {
        $this->ensureSchoolOwnerRole();
        $email = 'conflict-'.uniqid().'@example.com';
        $key = (string) \Illuminate\Support\Str::uuid();

        $p1 = $this->validPayload($email, 'Escola A');
        $p2 = $this->validPayload($email, 'Escola B');

        $this->postJson('/api/v1/auth/sign-up', $p1, ['Idempotency-Key' => $key])->assertOk();

        $r2 = $this->postJson('/api/v1/auth/sign-up', $p2, ['Idempotency-Key' => $key]);
        $r2->assertStatus(409);
        $r2->assertJsonFragment(['error' => 'idempotency_conflict']);
    }

    public function test_validation_fails_without_school_name_and_organization_name(): void
    {
        $this->ensureSchoolOwnerRole();
        $email = 'norg-'.uniqid().'@example.com';
        $payload = $this->validPayload($email);
        unset($payload['organization_name']);

        $response = $this->postJson('/api/v1/auth/sign-up', $payload);
        $response->assertStatus(422);
    }

    public function test_validation_fails_for_invalid_email_when_type_is_email(): void
    {
        $this->ensureSchoolOwnerRole();
        $payload = $this->validPayload('not-an-email', 'Escola X');
        $response = $this->postJson('/api/v1/auth/sign-up', $payload);
        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['identifier']]);
    }

    public function test_invalid_idempotency_key_format_returns_422(): void
    {
        $this->ensureSchoolOwnerRole();
        $email = 'idk-'.uniqid().'@example.com';
        $response = $this->postJson(
            '/api/v1/auth/sign-up',
            $this->validPayload($email),
            ['Idempotency-Key' => 'not-a-uuid']
        );
        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['idempotency_key']]);
    }

    public function test_transaction_rolls_back_and_leaves_no_user_when_tenant_step_fails(): void
    {
        $this->ensureSchoolOwnerRole();
        config(['iedu.testing_abort_after_user_create' => true]);

        try {
            $email = 'abort-'.uniqid().'@example.com';
            $response = $this->postJson('/api/v1/auth/sign-up', $this->validPayload($email));
            $response->assertStatus(500);
            $this->assertSame(0, User::query()->count());
            $this->assertSame(0, Tenant::query()->count());
        } finally {
            config(['iedu.testing_abort_after_user_create' => false]);
        }
    }
}
