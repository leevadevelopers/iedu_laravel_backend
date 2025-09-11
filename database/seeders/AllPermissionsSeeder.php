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

            // Transport permissions
            'view-transport',
            'create-transport',
            'edit-transport',
            'delete-transport',

            'view-students',



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

        $role = Role::where('guard_name', 'api')->first();
        if ($role) {
            $role->syncPermissions(Permission::all());
        }
    }
}
