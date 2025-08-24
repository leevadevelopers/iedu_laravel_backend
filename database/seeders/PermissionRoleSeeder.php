<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createRoles();
        $this->assignPermissionsToRoles();
    }


    private function createRoles(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Has complete access to all system features',
                'is_system' => true,
            ],
            [
                'name' => 'owner',
                'display_name' => 'Organization Owner',
                'description' => 'Owner of the organization with full access',
                'is_system' => true,
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Administrative access to most features',
                'is_system' => false,
            ],
            [
                'name' => 'student',
                'display_name' => 'Student',
                'description' => 'Student access to the system',
                'is_system' => false,
            ],
            [
                'name' => 'teacher',
                'display_name' => 'Finance Manager',
                'description' => 'Teacher access to the system',
                'is_system' => false,
            ],
            [
                'name' => 'parent',
                'display_name' => 'Team Member',
                'description' => 'Parent access to the system',
                'is_system' => false,
            ],
            [
                'name' => 'guest',
                'display_name' => 'Viewer',
                'description' => 'Guest access to the system',
                'is_system' => false,
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name'], 'guard_name' => 'api'],
                $role
            );
        }
    }

    private function assignPermissionsToRoles(): void
    {
        // Super Admin - All permissions
        $superAdmin = Role::where('name', 'super_admin')->first();
        $superAdmin->givePermissionTo(Permission::all());

        // Owner - All permissions except super admin functions
        $owner = Role::where('name', 'owner')->first();
        $ownerPermissions = Permission::whereNotIn('category', ['admin'])->get();
        $owner->givePermissionTo($ownerPermissions);


    }
}
