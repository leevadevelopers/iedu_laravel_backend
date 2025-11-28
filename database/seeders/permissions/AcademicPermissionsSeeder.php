<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AcademicPermissionsSeeder extends Seeder
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
     * Academic module permissions grouped in a single place.
     */
    private function permissions(): array
    {
        return [
            // Subjects
            'academic.subjects.view',
            'academic.subjects.create',
            'academic.subjects.edit',
            'academic.subjects.delete',
            'academic.subjects.view_all',
            'academic.subjects.create_all',
            'academic.subjects.edit_all',
            'academic.subjects.delete_all',

            // Classes
            'academic.classes.view',
            'academic.classes.create',
            'academic.classes.edit',
            'academic.classes.delete',
            'academic.classes.enroll',
            'academic.classes.remove',

            // Grading Systems
            'academic.grading-systems.view',
            'academic.grading-systems.create',
            'academic.grading-systems.edit',
            'academic.grading-systems.delete',
            'academic.grading-systems.set-primary',

            // Grade Scales
            'academic.grade-scales.view',
            'academic.grade-scales.create',
            'academic.grade-scales.edit',
            'academic.grade-scales.delete',
            'academic.grade-scales.set-default',

            // Grade Levels
            'academic.grade-levels.view',
            'academic.grade-levels.create',
            'academic.grade-levels.edit',
            'academic.grade-levels.delete',
            'academic.grade-levels.reorder',

            // Grade Entries
            'academic.grade-entries.view',
            'academic.grade-entries.create',
            'academic.grade-entries.edit',
            'academic.grade-entries.delete',
            'academic.grade-entries.bulk',

            // Teachers
            'academic.teachers.view',
            'academic.teachers.create',
            'academic.teachers.edit',
            'academic.teachers.delete',
            'academic.teachers.assign',

            // Analytics
            'academic.analytics.view',
            'academic.analytics.export',

            // Bulk Operations
            'academic.bulk.create-classes',
            'academic.bulk.enroll-students',
            'academic.bulk.import-grades',
            'academic.bulk.generate-reports',
            'academic.bulk.update-students',
            'academic.bulk.create-teachers',
            'academic.bulk.create-subjects',
            'academic.bulk.transfer-students',
        ];
    }
}
