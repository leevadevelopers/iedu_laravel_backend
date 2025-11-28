<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AllPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->permissionCategories() as $category => $permissions) {
            foreach ($permissions as $permission) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'guard_name' => 'api'],
                    ['category' => $category]
                );
            }
        }
    }

    /**
     * Core system permissions grouped by domain (non-module specific).
     */
    private function permissionCategories(): array
    {
        return [
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
            'schools' => [
                'schools.view',
                'schools.create',
                'schools.edit',
                'schools.delete',
                'schools.view_all',
                'schools.statistics',
            ],
        ];
    }
}
