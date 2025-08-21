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

        $this->createPermissions();
        $this->createRoles();
        $this->assignPermissionsToRoles();
    }

    private function createPermissions(): void
    {
        $permissions = [
            // Tenant Management
            ['name' => 'tenants.view', 'category' => 'tenants', 'description' => 'View tenant information'],
            ['name' => 'tenants.create', 'category' => 'tenants', 'description' => 'Create new tenants'],
            ['name' => 'tenants.edit', 'category' => 'tenants', 'description' => 'Edit tenant information'],
            ['name' => 'tenants.delete', 'category' => 'tenants', 'description' => 'Delete tenants'],
            ['name' => 'tenants.manage_users', 'category' => 'tenants', 'description' => 'Manage tenant users'],
            ['name' => 'tenants.manage_settings', 'category' => 'tenants', 'description' => 'Manage tenant settings'],
            
            // User Management
            ['name' => 'users.view', 'category' => 'users', 'description' => 'View users'],
            ['name' => 'users.create', 'category' => 'users', 'description' => 'Create new users'],
            ['name' => 'users.edit', 'category' => 'users', 'description' => 'Edit user information'],
            ['name' => 'users.delete', 'category' => 'users', 'description' => 'Delete users'],
            ['name' => 'users.manage_roles', 'category' => 'users', 'description' => 'Manage user roles'],
            ['name' => 'users.manage_permissions', 'category' => 'users', 'description' => 'Manage user permissions'],
            
            // Form Engine
            ['name' => 'forms.view', 'category' => 'forms', 'description' => 'View forms'],
            ['name' => 'forms.create', 'category' => 'forms', 'description' => 'Create new forms'],
            ['name' => 'forms.edit', 'category' => 'forms', 'description' => 'Edit forms'],
            ['name' => 'forms.delete', 'category' => 'forms', 'description' => 'Delete forms'],
            ['name' => 'forms.submit', 'category' => 'forms', 'description' => 'Submit forms'],
            ['name' => 'forms.approve', 'category' => 'forms', 'description' => 'Approve form submissions'],
            
            // Project Management
            ['name' => 'projects.view', 'category' => 'projects', 'description' => 'View projects'],
            ['name' => 'projects.create', 'category' => 'projects', 'description' => 'Create new projects'],
            ['name' => 'projects.edit', 'category' => 'projects', 'description' => 'Edit projects'],
            ['name' => 'projects.delete', 'category' => 'projects', 'description' => 'Delete projects'],
            ['name' => 'projects.manage_team', 'category' => 'projects', 'description' => 'Manage project team'],
            
            // Finance
            ['name' => 'finance.view', 'category' => 'finance', 'description' => 'View financial data'],
            ['name' => 'finance.create', 'category' => 'finance', 'description' => 'Create financial records'],
            ['name' => 'finance.edit', 'category' => 'finance', 'description' => 'Edit financial records'],
            ['name' => 'finance.approve_budget', 'category' => 'finance', 'description' => 'Approve budgets'],
            
            // Wildcard permissions
            ['name' => 'projects.*', 'category' => 'projects', 'description' => 'All project permissions'],
            ['name' => 'finance.*', 'category' => 'finance', 'description' => 'All finance permissions'],
            ['name' => 'admin.*', 'category' => 'admin', 'description' => 'All admin permissions'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'api'],
                $permission
            );
        }
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
                'name' => 'project_manager',
                'display_name' => 'Project Manager',
                'description' => 'Manages individual projects and teams',
                'is_system' => false,
            ],
            [
                'name' => 'finance_manager',
                'display_name' => 'Finance Manager',
                'description' => 'Manages financial aspects',
                'is_system' => false,
            ],
            [
                'name' => 'team_member',
                'display_name' => 'Team Member',
                'description' => 'Basic team member access',
                'is_system' => false,
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Viewer',
                'description' => 'Read-only access',
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

        // Project Manager
        $projectManager = Role::where('name', 'project_manager')->first();
        $projectManager->givePermissionTo([
            'projects.*', 'forms.view', 'forms.submit', 'users.view'
        ]);

        // Finance Manager
        $financeManager = Role::where('name', 'finance_manager')->first();
        $financeManager->givePermissionTo([
            'finance.*', 'projects.view', 'forms.view'
        ]);

        // Team Member
        $teamMember = Role::where('name', 'team_member')->first();
        $teamMember->givePermissionTo([
            'projects.view', 'forms.view', 'forms.submit'
        ]);

        // Viewer
        $viewer = Role::where('name', 'viewer')->first();
        $viewer->givePermissionTo([
            'projects.view', 'forms.view', 'finance.view'
        ]);
    }
}