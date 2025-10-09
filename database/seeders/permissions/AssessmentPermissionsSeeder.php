<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AssessmentPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assessment Module Permissions
        $assessmentPermissions = [
            // Assessments
            'assessments.view',
            'assessments.create',
            'assessments.edit',
            'assessments.delete',
            'assessments.publish',
            'assessments.archive',

            'assessment.assessments.view',
            'assessment.assessments.create',
            'assessment.assessments.edit',
            'assessment.assessments.delete',
            'assessment.assessments.publish',
            'assessment.assessments.archive',

            // Assessment Terms
            'assessment.terms.view',
            'assessment.terms.create',
            'assessment.terms.edit',
            'assessment.terms.delete',
            'assessment.terms.activate',

            // Assessment Types
            'assessment.types.view',
            'assessment.types.create',
            'assessment.types.edit',
            'assessment.types.delete',

            // Assessment Components
            'assessment.components.view',
            'assessment.components.create',
            'assessment.components.edit',
            'assessment.components.delete',

            // Grades
            'assessment.grades.view',
            'assessment.grades.enter',
            'assessment.grades.edit',
            'assessment.grades.delete',
            'assessment.grades.bulk-import',
            'assessment.grades.publish',
            'assessment.grades.export',

            // Gradebooks
            'assessment.gradebooks.view',
            'assessment.gradebooks.upload',
            'assessment.gradebooks.download',
            'assessment.gradebooks.manage',

            // Grade Reviews
            'assessment.grade-reviews.view',
            'assessment.grade-reviews.create',
            'assessment.grade-reviews.resolve',
            'assessment.grade-reviews.manage',

            // Grade Scales
            'assessment.grade-scales.view',
            'assessment.grade-scales.create',
            'assessment.grade-scales.edit',
            'assessment.grade-scales.delete',
            'assessment.grade-scales.manage',

            // Assessment Resources
            'assessment.resources.view',
            'assessment.resources.upload',
            'assessment.resources.download',
            'assessment.resources.delete',

            // Assessment Settings
            'assessment.settings.view',
            'assessment.settings.manage',

            // Audit Logs
            'assessment.audit-logs.view',
            'assessment.audit-logs.export',

            // Analytics & Reports
            'assessment.analytics.view',
            'assessment.analytics.export',
            'assessment.reports.generate',
            'assessment.reports.view',

            // Bulk Operations
            'assessment.bulk.import-assessments',
            'assessment.bulk.import-grades',
            'assessment.bulk.publish-grades',
            'assessment.bulk.export-data',
        ];

        // Create permissions
        foreach ($assessmentPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Create roles and assign permissions
        $this->createRoles();
    }

    private function createRoles(): void
    {
        // Assessment Administrator - Full access to assessment module
        $assessmentAdmin = Role::firstOrCreate([
            'name' => 'Assessment Administrator',
            'guard_name' => 'api'
        ]);

        $assessmentAdmin->givePermissionTo([
            'assessment.assessments.view',
            'assessment.assessments.create',
            'assessment.assessments.edit',
            'assessment.assessments.delete',
            'assessment.assessments.publish',
            'assessment.assessments.archive',
            'assessment.terms.view',
            'assessment.terms.create',
            'assessment.terms.edit',
            'assessment.terms.delete',
            'assessment.terms.activate',
            'assessment.types.view',
            'assessment.types.create',
            'assessment.types.edit',
            'assessment.types.delete',
            'assessment.components.view',
            'assessment.components.create',
            'assessment.components.edit',
            'assessment.components.delete',
            'assessment.grades.view',
            'assessment.grades.enter',
            'assessment.grades.edit',
            'assessment.grades.delete',
            'assessment.grades.bulk-import',
            'assessment.grades.publish',
            'assessment.grades.export',
            'assessment.gradebooks.view',
            'assessment.gradebooks.upload',
            'assessment.gradebooks.download',
            'assessment.gradebooks.manage',
            'assessment.grade-reviews.view',
            'assessment.grade-reviews.create',
            'assessment.grade-reviews.resolve',
            'assessment.grade-reviews.manage',
            'assessment.grade-scales.view',
            'assessment.grade-scales.create',
            'assessment.grade-scales.edit',
            'assessment.grade-scales.delete',
            'assessment.grade-scales.manage',
            'assessment.resources.view',
            'assessment.resources.upload',
            'assessment.resources.download',
            'assessment.resources.delete',
            'assessment.settings.view',
            'assessment.settings.manage',
            'assessment.audit-logs.view',
            'assessment.audit-logs.export',
            'assessment.analytics.view',
            'assessment.analytics.export',
            'assessment.reports.generate',
            'assessment.reports.view',
            'assessment.bulk.import-assessments',
            'assessment.bulk.import-grades',
            'assessment.bulk.publish-grades',
            'assessment.bulk.export-data',
        ]);

        // Academic Coordinator - Management level access
        $coordinator = Role::firstOrCreate([
            'name' => 'Academic Coordinator',
            'guard_name' => 'api'
        ]);

        $coordinator->givePermissionTo([
            'assessment.assessments.view',
            'assessment.assessments.create',
            'assessment.assessments.edit',
            'assessment.assessments.delete',
            'assessment.assessments.publish',
            'assessment.terms.view',
            'assessment.terms.create',
            'assessment.terms.edit',
            'assessment.types.view',
            'assessment.types.create',
            'assessment.types.edit',
            'assessment.components.view',
            'assessment.components.create',
            'assessment.components.edit',
            'assessment.grades.view',
            'assessment.grades.enter',
            'assessment.grades.edit',
            'assessment.grades.bulk-import',
            'assessment.grades.publish',
            'assessment.grades.export',
            'assessment.gradebooks.view',
            'assessment.gradebooks.upload',
            'assessment.gradebooks.download',
            'assessment.gradebooks.manage',
            'assessment.grade-reviews.view',
            'assessment.grade-reviews.create',
            'assessment.grade-reviews.resolve',
            'assessment.grade-reviews.manage',
            'assessment.grade-scales.view',
            'assessment.grade-scales.create',
            'assessment.grade-scales.edit',
            'assessment.grade-scales.manage',
            'assessment.resources.view',
            'assessment.resources.upload',
            'assessment.resources.download',
            'assessment.settings.view',
            'assessment.settings.manage',
            'assessment.analytics.view',
            'assessment.analytics.export',
            'assessment.reports.generate',
            'assessment.reports.view',
            'assessment.bulk.import-assessments',
            'assessment.bulk.import-grades',
            'assessment.bulk.publish-grades',
            'assessment.bulk.export-data',
        ]);

        // Teacher - Can manage own assessments and grades
        $teacher = Role::firstOrCreate([
            'name' => 'Teacher',
            'guard_name' => 'api'
        ]);

        $teacher->givePermissionTo([
            'assessment.assessments.view',
            'assessment.assessments.create',
            'assessment.assessments.edit',
            'assessment.assessments.delete',
            'assessment.terms.view',
            'assessment.types.view',
            'assessment.components.view',
            'assessment.components.create',
            'assessment.components.edit',
            'assessment.grades.view',
            'assessment.grades.enter',
            'assessment.grades.edit',
            'assessment.grades.bulk-import',
            'assessment.grades.export',
            'assessment.gradebooks.view',
            'assessment.gradebooks.upload',
            'assessment.gradebooks.download',
            'assessment.grade-reviews.view',
            'assessment.resources.view',
            'assessment.resources.upload',
            'assessment.resources.download',
            'assessment.analytics.view',
            'assessment.reports.view',
        ]);

        // Student - Can view own grades and assessments
        $student = Role::firstOrCreate([
            'name' => 'Student',
            'guard_name' => 'api'
        ]);

        $student->givePermissionTo([
            'assessment.assessments.view',
            'assessment.grades.view',
            'assessment.resources.view',
            'assessment.resources.download',
            'assessment.grade-reviews.create',
        ]);

        // Parent - Can view children's grades
        $parent = Role::firstOrCreate([
            'name' => 'Parent',
            'guard_name' => 'api'
        ]);

        $parent->givePermissionTo([
            'assessment.assessments.view',
            'assessment.grades.view',
            'assessment.analytics.view',
            'assessment.reports.view',
        ]);
    }
}

