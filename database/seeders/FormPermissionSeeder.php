<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FormPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createFormPermissions();
        $this->createFormRoles();
        $this->assignFormPermissionsToRoles();
    }

    private function createFormPermissions(): void
    {
        $formPermissions = [
            // Form Templates
            ['name' => 'form_templates.view', 'category' => 'forms', 'description' => 'View form templates'],
            ['name' => 'form_templates.create', 'category' => 'forms', 'description' => 'Create new form templates'],
            ['name' => 'form_templates.edit', 'category' => 'forms', 'description' => 'Edit form templates'],
            ['name' => 'form_templates.delete', 'category' => 'forms', 'description' => 'Delete form templates'],
            ['name' => 'form_templates.publish', 'category' => 'forms', 'description' => 'Publish form templates'],
            ['name' => 'form_templates.archive', 'category' => 'forms', 'description' => 'Archive form templates'],
            ['name' => 'form_templates.duplicate', 'category' => 'forms', 'description' => 'Duplicate form templates'],
            
            // Form Instances
            ['name' => 'form_instances.view', 'category' => 'forms', 'description' => 'View form instances'],
            ['name' => 'form_instances.create', 'category' => 'forms', 'description' => 'Create new form instances'],
            ['name' => 'form_instances.edit', 'category' => 'forms', 'description' => 'Edit form instances'],
            ['name' => 'form_instances.delete', 'category' => 'forms', 'description' => 'Delete form instances'],
            ['name' => 'form_instances.submit', 'category' => 'forms', 'description' => 'Submit form instances'],
            ['name' => 'form_instances.approve', 'category' => 'forms', 'description' => 'Approve form instances'],
            ['name' => 'form_instances.reject', 'category' => 'forms', 'description' => 'Reject form instances'],
            ['name' => 'form_instances.review', 'category' => 'forms', 'description' => 'Review form instances'],
            
            // Form Submissions
            ['name' => 'form_submissions.view', 'category' => 'forms', 'description' => 'View form submissions'],
            ['name' => 'form_submissions.create', 'category' => 'forms', 'description' => 'Create form submissions'],
            ['name' => 'form_submissions.edit', 'category' => 'forms', 'description' => 'Edit form submissions'],
            ['name' => 'form_submissions.delete', 'category' => 'forms', 'description' => 'Delete form submissions'],
            ['name' => 'form_submissions.export', 'category' => 'forms', 'description' => 'Export form submissions'],
            
            // Form Analytics
            ['name' => 'form_analytics.view', 'category' => 'forms', 'description' => 'View form analytics'],
            ['name' => 'form_analytics.export', 'category' => 'forms', 'description' => 'Export form analytics'],
            
            // Form Workflow
            ['name' => 'form_workflow.manage', 'category' => 'forms', 'description' => 'Manage form workflows'],
            ['name' => 'form_workflow.approve', 'category' => 'forms', 'description' => 'Approve workflow steps'],
            ['name' => 'form_workflow.reject', 'category' => 'forms', 'description' => 'Reject workflow steps'],
            
            // Form Compliance
            ['name' => 'form_compliance.view', 'category' => 'forms', 'description' => 'View compliance reports'],
            ['name' => 'form_compliance.manage', 'category' => 'forms', 'description' => 'Manage compliance settings'],
            
            // Wildcard permissions
            ['name' => 'form_templates.*', 'category' => 'forms', 'description' => 'All form template permissions'],
            ['name' => 'form_instances.*', 'category' => 'forms', 'description' => 'All form instance permissions'],
            ['name' => 'form_submissions.*', 'category' => 'forms', 'description' => 'All form submission permissions'],
            ['name' => 'forms.*', 'category' => 'forms', 'description' => 'All form permissions'],
        ];

        foreach ($formPermissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'api'],
                $permission
            );
        }
    }

    private function createFormRoles(): void
    {
        $formRoles = [
            [
                'name' => 'form_designer',
                'display_name' => 'Form Designer',
                'description' => 'Can create and edit form templates',
                'is_system' => false,
            ],
            [
                'name' => 'form_reviewer',
                'display_name' => 'Form Reviewer',
                'description' => 'Can review and approve form submissions',
                'is_system' => false,
            ],
            [
                'name' => 'form_submitter',
                'display_name' => 'Form Submitter',
                'description' => 'Can submit forms and view own submissions',
                'is_system' => false,
            ],
            [
                'name' => 'form_analyst',
                'display_name' => 'Form Analyst',
                'description' => 'Can view analytics and export data',
                'is_system' => false,
            ],
        ];

        foreach ($formRoles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name'], 'guard_name' => 'api'],
                $role
            );
        }
    }

    private function assignFormPermissionsToRoles(): void
    {
        // Form Designer - Can create and manage templates
        $formDesigner = Role::where('name', 'form_designer')->first();
        $formDesigner->givePermissionTo([
            'form_templates.view', 'form_templates.create', 'form_templates.edit', 
            'form_templates.delete', 'form_templates.publish', 'form_templates.archive', 
            'form_templates.duplicate', 'form_instances.view', 'form_submissions.view'
        ]);

        // Form Reviewer - Can review and approve submissions
        $formReviewer = Role::where('name', 'form_reviewer')->first();
        $formReviewer->givePermissionTo([
            'form_templates.view', 'form_instances.view', 'form_instances.create', 
            'form_instances.edit', 'form_instances.delete', 'form_instances.submit', 
            'form_instances.approve', 'form_instances.reject', 'form_instances.review',
            'form_submissions.view', 'form_submissions.create', 'form_submissions.edit', 
            'form_submissions.delete', 'form_submissions.export', 'form_analytics.view'
        ]);

        // Form Submitter - Can submit forms
        $formSubmitter = Role::where('name', 'form_submitter')->first();
        $formSubmitter->givePermissionTo([
            'form_templates.view', 'form_instances.create', 'form_instances.edit', 
            'form_instances.submit', 'form_submissions.create', 'form_submissions.view'
        ]);

        // Form Analyst - Can view analytics
        $formAnalyst = Role::where('name', 'form_analyst')->first();
        $formAnalyst->givePermissionTo([
            'form_templates.view', 'form_instances.view', 'form_submissions.view',
            'form_analytics.view', 'form_analytics.export', 'form_submissions.export'
        ]);

        // Update existing roles with form permissions
        $this->updateExistingRolesWithFormPermissions();
    }

    private function updateExistingRolesWithFormPermissions(): void
    {
        // Super Admin - Already has all permissions via wildcard
        
        // Owner - Add form permissions
        $owner = Role::where('name', 'owner')->first();
        if ($owner) {
            $owner->givePermissionTo([
                'form_templates.view', 'form_templates.create', 'form_templates.edit', 
                'form_templates.delete', 'form_templates.publish', 'form_templates.archive', 
                'form_templates.duplicate', 'form_instances.view', 'form_instances.create', 
                'form_instances.edit', 'form_instances.delete', 'form_instances.submit', 
                'form_instances.approve', 'form_instances.reject', 'form_instances.review',
                'form_submissions.view', 'form_submissions.create', 'form_submissions.edit', 
                'form_submissions.delete', 'form_submissions.export', 'form_analytics.view', 
                'form_analytics.export', 'form_workflow.manage', 'form_workflow.approve', 
                'form_workflow.reject', 'form_compliance.view', 'form_compliance.manage'
            ]);
        }

        // Admin - Add comprehensive form permissions
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'form_templates.view', 'form_templates.create', 'form_templates.edit', 
                'form_templates.delete', 'form_templates.publish', 'form_templates.archive', 
                'form_templates.duplicate', 'form_instances.view', 'form_instances.create', 
                'form_instances.edit', 'form_instances.delete', 'form_instances.submit', 
                'form_instances.approve', 'form_instances.reject', 'form_instances.review',
                'form_submissions.view', 'form_submissions.create', 'form_submissions.edit', 
                'form_submissions.delete', 'form_submissions.export', 'form_analytics.view', 
                'form_workflow.manage', 'form_compliance.view'
            ]);
        }

        // Project Manager - Add form submission and review permissions
        $projectManager = Role::where('name', 'project_manager')->first();
        if ($projectManager) {
            $projectManager->givePermissionTo([
                'form_templates.view', 'form_instances.view', 'form_instances.create', 
                'form_instances.edit', 'form_instances.delete', 'form_instances.submit', 
                'form_instances.approve', 'form_instances.reject', 'form_instances.review',
                'form_submissions.view', 'form_submissions.create', 'form_submissions.edit', 
                'form_submissions.delete', 'form_submissions.export', 'form_analytics.view', 
                'form_workflow.approve', 'form_workflow.reject'
            ]);
        }

        // Team Member - Add basic form permissions
        $teamMember = Role::where('name', 'team_member')->first();
        if ($teamMember) {
            $teamMember->givePermissionTo([
                'form_templates.view', 'form_instances.create', 'form_instances.edit',
                'form_instances.submit', 'form_submissions.create', 'form_submissions.view'
            ]);
        }

        // Viewer - Add view-only form permissions
        $viewer = Role::where('name', 'viewer')->first();
        if ($viewer) {
            $viewer->givePermissionTo([
                'form_templates.view', 'form_instances.view', 'form_submissions.view'
            ]);
        }
    }
} 