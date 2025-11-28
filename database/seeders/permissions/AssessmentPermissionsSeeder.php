<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AssessmentPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }
    }

    /**
     * Assessment module permissions grouped by capability.
     */
    private function permissions(): array
    {
        return [
            // Legacy assessment aliases
            'assessments.view',
            'assessments.create',
            'assessments.edit',
            'assessments.delete',
            'assessments.publish',
            'assessments.archive',

            // Assessments
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
    }
}
