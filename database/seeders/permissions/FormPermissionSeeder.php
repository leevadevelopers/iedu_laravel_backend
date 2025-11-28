<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class FormPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->formPermissions() as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'api'],
                $permission
            );
        }
    }

    /**
     * Detailed listing of all form-related permissions grouped by area.
     */
    private function formPermissions(): array
    {
        return [
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
    }
}
