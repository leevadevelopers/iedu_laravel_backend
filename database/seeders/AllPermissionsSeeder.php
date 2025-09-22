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

            'view-transport-subscriptions',
            'create-transport-subscriptions',
            'edit-transport-subscriptions',
            'delete-transport-subscriptions',

            'view-own-students',

            // Academic permissions
            'academic.view',
            'academic.create',
            'academic.edit',
            'academic.delete',
            'academic.admin',

            // Subject permissions
            'subjects.view',
            'subjects.create',
            'subjects.edit',
            'subjects.delete',
            'subjects.restore',
            'subjects.force_delete',
            'subjects.admin',

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
            'transport' => [
                'view-transport',
                'create-transport',
                'edit-transport',
                'delete-transport',
            ],
            'transport-subscriptions' => [
                'view-transport-subscriptions',
                'create-transport-subscriptions',
                'edit-transport-subscriptions',
                'delete-transport-subscriptions',
            ],
            'own-students' => [
                'view-own-students',
            ],
            'academic' => [
                'academic.view',
                'academic.create',
                'academic.edit',
                'academic.delete',
                'academic.admin',
            ],
            'subjects' => [
                'subjects.view',
                'subjects.create',
                'subjects.edit',
                'subjects.delete',
                'subjects.restore',
                'subjects.force_delete',
                'subjects.admin',
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
