<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Settings\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all tenants and users
        $tenants = Tenant::all();
        $users = User::all();

        // Create tenant-user relationships
        $tenantUsers = [
            // Acme Corporation (Tenant ID: 1)
            [
                'tenant_id' => 1,
                'user_id' => 1, // Admin User
                'role_id' => 1, // Assuming role_id 1 is admin
                'permissions' => json_encode(['*']), // All permissions
                'current_tenant' => true,
                'status' => 'active'
            ],
            [
                'tenant_id' => 1,
                'user_id' => 2, // John Doe
                'role_id' => 2, // Assuming role_id 2 is user
                'permissions' => json_encode(['read', 'write']),
                'current_tenant' => false,
                'status' => 'active'
            ],
            [
                'tenant_id' => 1,
                'user_id' => 7, // Diana Prince
                'role_id' => 2,
                'permissions' => json_encode(['read', 'write']),
                'current_tenant' => false,
                'status' => 'active'
            ],

            // TechStart Inc (Tenant ID: 2)
            [
                'tenant_id' => 2,
                'user_id' => 1, // Admin User (can be in multiple tenants)
                'role_id' => 1,
                'permissions' => json_encode(['*']),
                'current_tenant' => false,
                'status' => 'active'
            ],
            [
                'tenant_id' => 2,
                'user_id' => 3, // Jane Smith
                'role_id' => 2,
                'permissions' => json_encode(['read', 'write']),
                'current_tenant' => true,
                'status' => 'active'
            ],
            [
                'tenant_id' => 2,
                'user_id' => 8, // Eve Adams
                'role_id' => 2,
                'permissions' => json_encode(['read']),
                'current_tenant' => false,
                'status' => 'active'
            ],

            // Global Solutions Ltd (Tenant ID: 3)
            [
                'tenant_id' => 3,
                'user_id' => 1, // Admin User
                'role_id' => 1,
                'permissions' => json_encode(['*']),
                'current_tenant' => false,
                'status' => 'active'
            ],
            [
                'tenant_id' => 3,
                'user_id' => 4, // Bob Wilson
                'role_id' => 2,
                'permissions' => json_encode(['read', 'write']),
                'current_tenant' => true,
                'status' => 'active'
            ],

            // Innovation Hub (Tenant ID: 4)
            [
                'tenant_id' => 4,
                'user_id' => 1, // Admin User
                'role_id' => 1,
                'permissions' => json_encode(['*']),
                'current_tenant' => false,
                'status' => 'active'
            ],
            [
                'tenant_id' => 4,
                'user_id' => 5, // Alice Johnson
                'role_id' => 2,
                'permissions' => json_encode(['read', 'write']),
                'current_tenant' => true,
                'status' => 'active'
            ],
            [
                'tenant_id' => 4,
                'user_id' => 6, // Charlie Brown
                'role_id' => 2,
                'permissions' => json_encode(['read']),
                'current_tenant' => false,
                'status' => 'active'
            ]
        ];

        foreach ($tenantUsers as $tenantUserData) {
            DB::table('tenant_users')->insert($tenantUserData);
        }
    }
} 