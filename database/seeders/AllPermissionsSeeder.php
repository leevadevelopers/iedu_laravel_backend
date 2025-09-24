<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AllPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Run specific permission seeders
        $this->call([
            Permissions\AcademicPermissionsSeeder::class,
            Permissions\TransportPermissionsSeeder::class,
        ]);

        // Core system permissions (not module-specific)
        $permissions = [
            // Forms permissions
            'forms.view',
            'forms.admin',
            'forms.view_all',
            'forms.create',
            'forms.edit_all',
            'forms.delete',
            'forms.workflow',
            'forms.create_template',
            'forms.edit_template',
            'forms.delete_template',
            'forms.manage_public_access',

            // Tenant permissions
            'tenants.create',
            'tenants.manage_users',
            'tenants.manage_settings',
            'tenants.view',

            // User permissions
            'users.view',
            'users.manage',
            'users.manage_roles',
            'users.manage_permissions',
            'users.create',
            'users.edit',
            'users.delete',

            // Team management permissions
            'teams.view',
            'teams.manage',
            'teams.invite',
            'teams.remove',
            'teams.assign_roles',
        ];

        $permissionCategories = [
            'forms' => [
                'forms.view',
                'forms.admin',
                'forms.view_all',
                'forms.create',
                'forms.edit_all',
                'forms.delete',
                'forms.workflow',
                'forms.create_template',
                'forms.edit_template',
                'forms.delete_template',
                'forms.manage_public_access',
            ],
            'tenants' => [
                'tenants.create',
                'tenants.manage_users',
                'tenants.manage_settings',
                'tenants.view',
            ],
            'users' => [
                'users.view',
                'users.manage',
                'users.manage_roles',
                'users.manage_permissions',
                'users.create',
                'users.edit',
                'users.delete',
            ],
            'teams' => [
                'teams.view',
                'teams.manage',
                'teams.invite',
                'teams.remove',
                'teams.assign_roles',
            ],
        ];

        foreach ($permissionCategories as $category => $categoryPermissions) {
            foreach ($categoryPermissions as $permission) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'guard_name' => 'api'],
                    ['category' => $category]
                );
            }
        }

        // Create core system roles
        $this->createCoreSystemRoles();
    }

    private function createCoreSystemRoles(): void
    {
        // Super Administrator - Full access to everything
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Administrator',
            'guard_name' => 'api'
        ]);

        $superAdmin->syncPermissions(Permission::all());

        // System Administrator - Core system permissions
        $systemAdmin = Role::firstOrCreate([
            'name' => 'System Administrator',
            'guard_name' => 'api'
        ]);

        $systemAdmin->givePermissionTo([
            // Forms
            'forms.view',
            'forms.admin',
            'forms.view_all',
            'forms.create',
            'forms.edit_all',
            'forms.delete',
            'forms.workflow',
            'forms.create_template',
            'forms.edit_template',
            'forms.delete_template',
            'forms.manage_public_access',

            // Users
            'users.view',
            'users.manage',
            'users.manage_roles',
            'users.manage_permissions',
            'users.create',
            'users.edit',
            'users.delete',

            // Teams
            'teams.view',
            'teams.manage',
            'teams.invite',
            'teams.remove',
            'teams.assign_roles',
        ]);

        // Tenant Administrator - Tenant management
        $tenantAdmin = Role::firstOrCreate([
            'name' => 'Tenant Administrator',
            'guard_name' => 'api'
        ]);

        $tenantAdmin->givePermissionTo([
            // Forms
            'forms.view',
            'forms.view_all',
            'forms.create',
            'forms.edit_all',
            'forms.delete',
            'forms.workflow',
            'forms.create_template',
            'forms.edit_template',
            'forms.delete_template',

            // Users (limited)
            'users.view',
            'users.create',
            'users.edit',

            // Teams
            'teams.view',
            'teams.manage',
            'teams.invite',
            'teams.remove',
            'teams.assign_roles',
        ]);
    }
}
