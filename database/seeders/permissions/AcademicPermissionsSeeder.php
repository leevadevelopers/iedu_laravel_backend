<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AcademicPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Academic Module Permissions
        $academicPermissions = [
            // Subjects
            'academic.subjects.view',
            'academic.subjects.create',
            'academic.subjects.edit',
            'academic.subjects.delete',

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

        // Create permissions
        foreach ($academicPermissions as $permission) {
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
        // Academic Administrator - Full access to academic module
        $academicAdmin = Role::firstOrCreate([
            'name' => 'Academic Administrator',
            'guard_name' => 'api'
        ]);

        $academicAdmin->givePermissionTo([
            'academic.subjects.view',
            'academic.subjects.create',
            'academic.subjects.edit',
            'academic.subjects.delete',
            'academic.classes.view',
            'academic.classes.create',
            'academic.classes.edit',
            'academic.classes.delete',
            'academic.classes.enroll',
            'academic.classes.remove',
            'academic.grading-systems.view',
            'academic.grading-systems.create',
            'academic.grading-systems.edit',
            'academic.grading-systems.delete',
            'academic.grading-systems.set-primary',
            'academic.grade-scales.view',
            'academic.grade-scales.create',
            'academic.grade-scales.edit',
            'academic.grade-scales.delete',
            'academic.grade-scales.set-default',
            'academic.grade-levels.view',
            'academic.grade-levels.create',
            'academic.grade-levels.edit',
            'academic.grade-levels.delete',
            'academic.grade-levels.reorder',
            'academic.grade-entries.view',
            'academic.grade-entries.create',
            'academic.grade-entries.edit',
            'academic.grade-entries.delete',
            'academic.grade-entries.bulk',
            'academic.teachers.view',
            'academic.teachers.create',
            'academic.teachers.edit',
            'academic.teachers.delete',
            'academic.teachers.assign',
            'academic.analytics.view',
            'academic.analytics.export',
            'academic.bulk.create-classes',
            'academic.bulk.enroll-students',
            'academic.bulk.import-grades',
            'academic.bulk.generate-reports',
            'academic.bulk.update-students',
            'academic.bulk.create-teachers',
            'academic.bulk.create-subjects',
            'academic.bulk.transfer-students',
        ]);

        // Teacher - Limited access for teaching activities
        $teacher = Role::firstOrCreate([
            'name' => 'Teacher',
            'guard_name' => 'api'
        ]);

        $teacher->givePermissionTo([
            'academic.subjects.view',
            'academic.classes.view',
            'academic.grade-entries.view',
            'academic.grade-entries.create',
            'academic.grade-entries.edit',
            'academic.grade-entries.delete',
            'academic.analytics.view',
        ]);

        // Academic Coordinator - Management level access
        $coordinator = Role::firstOrCreate([
            'name' => 'Academic Coordinator',
            'guard_name' => 'api'
        ]);

        $coordinator->givePermissionTo([
            'academic.subjects.view',
            'academic.subjects.create',
            'academic.subjects.edit',
            'academic.classes.view',
            'academic.classes.create',
            'academic.classes.edit',
            'academic.classes.enroll',
            'academic.classes.remove',
            'academic.grading-systems.view',
            'academic.grading-systems.create',
            'academic.grading-systems.edit',
            'academic.grade-scales.view',
            'academic.grade-scales.create',
            'academic.grade-scales.edit',
            'academic.grade-levels.view',
            'academic.grade-levels.create',
            'academic.grade-levels.edit',
            'academic.grade-levels.reorder',
            'academic.grade-entries.view',
            'academic.grade-entries.create',
            'academic.grade-entries.edit',
            'academic.grade-entries.bulk',
            'academic.teachers.view',
            'academic.teachers.create',
            'academic.teachers.edit',
            'academic.teachers.assign',
            'academic.analytics.view',
            'academic.analytics.export',
        ]);

        // Student - Read-only access
        $student = Role::firstOrCreate([
            'name' => 'Student',
            'guard_name' => 'api'
        ]);

        $student->givePermissionTo([
            'academic.subjects.view',
            'academic.classes.view',
            'academic.grade-entries.view',
        ]);
    }
}
