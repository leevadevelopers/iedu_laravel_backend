<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    private const ALL_PERMISSIONS = '__ALL__';
    private const NON_ADMIN_PERMISSIONS = '__NON_ADMIN__';

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->roleDefinitions() as $name => $meta) {
            Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                [
                    'display_name' => $meta['display_name'] ?? $name,
                    'description' => $meta['description'] ?? null,
                    'is_system' => $meta['is_system'] ?? false,
                ]
            );
        }

        $this->assignPermissions();

        $this->command?->info('Roles and permissions assigned successfully!');
    }

    private function assignPermissions(): void
    {
        $allPermissions = Permission::where('guard_name', 'api')->get();

        $nonAdminPermissions = Permission::where('guard_name', 'api')
            ->where(function ($query) {
                $query->whereNull('category')->orWhereNotIn('category', ['admin']);
            })
            ->get();

        foreach ($this->rolePermissions() as $roleName => $permissions) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'api')
                ->first();

            if (!$role) {
                continue;
            }

            if ($permissions === self::ALL_PERMISSIONS) {
                $role->syncPermissions($allPermissions);
                continue;
            }

            if ($permissions === self::NON_ADMIN_PERMISSIONS) {
                $role->syncPermissions($nonAdminPermissions);
                continue;
            }

            $role->syncPermissions($this->normalizePermissions($permissions));
        }
    }

    private function normalizePermissions(array $permissions): array
    {
        return array_values(array_unique($permissions));
    }

    private function roleDefinitions(): array
    {
        return [
            'super_admin' => [
                'display_name' => 'Super Administrador',
                'description' => 'Leeva/iEDU Team - Platform-wide access, manages all schools, system-wide analytics, billing/subscriptions, support, and global settings',
                'is_system' => true,
            ],
            'school_owner' => [
                'display_name' => 'Dono da Escola',
                'description' => 'Full control of their school(s), manages subscriptions/billing, adds/removes staff, configures school settings, accesses all reports. Multi-school access if Premium',
                'is_system' => true,
            ],
            'school_admin' => [
                'display_name' => 'Director/Administrador',
                'description' => 'Manages students, teachers, staff. Configures academic year, classes, subjects. Manages fees/payments. Generates reports. Cannot change subscription or owner settings',
                'is_system' => false,
            ],
            'teacher' => [
                'display_name' => 'Professor',
                'description' => 'Views assigned classes only, marks attendance, enters/edits grades for their subjects, views student info for their classes, communicates with parents',
                'is_system' => false,
            ],
            'parent' => [
                'display_name' => 'Encarregado de Educação',
                'description' => 'Views only their children\'s data, sees grades, attendance, fees. Makes payments. Receives notifications. Communicates with teachers/school',
                'is_system' => false,
            ],
            'student' => [
                'display_name' => 'Aluno',
                'description' => 'Views own grades/attendance, accesses learning materials, limited portal access',
                'is_system' => false,
            ],
            'accountant' => [
                'display_name' => 'Contabilista',
                'description' => 'Manages fees/payments only, generates financial reports, views payment history. Cannot access academic data',
                'is_system' => false,
            ],
            'secretary' => [
                'display_name' => 'Secretária',
                'description' => 'Registers students, updates contact info, basic attendance marking. Cannot manage fees or grades',
                'is_system' => false,
            ],
        ];
    }

    private function rolePermissions(): array
    {
        return [
            'super_admin' => self::ALL_PERMISSIONS,
            'school_owner' => $this->schoolOwnerPermissions(),
            'school_admin' => $this->schoolAdminPermissions(),
            'teacher' => array_merge(
                $this->teacherLibraryPermissions(),
                $this->academicTeacherPermissions(),
                $this->assessmentTeacherPermissions(),
                $this->schoolViewPermissions()
            ),
            'parent' => array_merge(
                $this->parentLibraryFinancePermissions(),
                $this->assessmentParentPermissions(),
                $this->transportParentPermissions(),
                $this->schoolViewPermissions()
            ),
            'student' => array_merge(
                $this->studentLibraryPermissions(),
                $this->academicStudentPermissions(),
                $this->assessmentStudentPermissions(),
                $this->schoolViewPermissions()
            ),
            'accountant' => $this->accountantPermissions(),
            'secretary' => $this->secretaryPermissions(),
        ];
    }

    private function tenantAdminPermissions(): array
    {
        return [
            'library.manage',
            'finance.manage',
        ];
    }

    private function libraryAdminPermissions(): array
    {
        return [
            'library.manage',
            'library.collections.view',
            'library.collections.create',
            'library.collections.update',
            'library.collections.delete',
            'library.authors.view',
            'library.authors.create',
            'library.authors.update',
            'library.authors.delete',
            'library.publishers.view',
            'library.publishers.create',
            'library.publishers.update',
            'library.publishers.delete',
            'library.books.view',
            'library.books.create',
            'library.books.update',
            'library.books.delete',
            'library.book-files.view',
            'library.book-files.create',
            'library.book-files.update',
            'library.book-files.delete',
            'library.book-files.download',
            'library.loans.view',
            'library.loans.create',
            'library.loans.manage',
            'library.loans.request',
            'library.loans.delete',
            'library.reservations.view',
            'library.reservations.create',
            'library.reservations.manage',
            'library.incidents.view',
            'library.incidents.create',
            'library.incidents.resolve',
        ];
    }

    private function financeAdminPermissions(): array
    {
        return [
            'finance.manage',
            'finance.accounts.view',
            'finance.accounts.create',
            'finance.accounts.update',
            'finance.accounts.delete',
            'finance.invoices.view',
            'finance.invoices.create',
            'finance.invoices.update',
            'finance.invoices.delete',
            'finance.invoices.issue',
            'finance.payments.view',
            'finance.payments.create',
            'finance.fees.view',
            'finance.fees.create',
            'finance.fees.update',
            'finance.fees.delete',
            'finance.fees.apply',
            'finance.expenses.view',
            'finance.expenses.create',
            'finance.expenses.update',
            'finance.expenses.delete',
            'finance.reports.view',
        ];
    }

    private function librarianPermissions(): array
    {
        return [
            'library.collections.view',
            'library.collections.create',
            'library.collections.update',
            'library.authors.view',
            'library.authors.create',
            'library.authors.update',
            'library.publishers.view',
            'library.publishers.create',
            'library.publishers.update',
            'library.books.view',
            'library.books.create',
            'library.books.update',
            'library.book-files.view',
            'library.book-files.create',
            'library.book-files.update',
            'library.book-files.delete',
            'library.book-files.download',
            'library.loans.view',
            'library.loans.create',
            'library.loans.manage',
            'library.reservations.view',
            'library.reservations.manage',
            'library.incidents.view',
            'library.incidents.resolve',
        ];
    }

    private function financeManagerPermissions(): array
    {
        return [
            'finance.accounts.view',
            'finance.accounts.create',
            'finance.accounts.update',
            'finance.accounts.delete',
            'finance.invoices.view',
            'finance.invoices.create',
            'finance.invoices.update',
            'finance.invoices.delete',
            'finance.invoices.issue',
            'finance.payments.view',
            'finance.payments.create',
            'finance.fees.view',
            'finance.fees.create',
            'finance.fees.update',
            'finance.fees.delete',
            'finance.fees.apply',
            'finance.expenses.view',
            'finance.expenses.create',
            'finance.expenses.update',
            'finance.expenses.delete',
            'finance.reports.view',
        ];
    }

    private function teacherLibraryPermissions(): array
    {
        return [
            'library.collections.view',
            'library.authors.view',
            'library.publishers.view',
            'library.books.view',
            'library.book-files.view',
            'library.book-files.download',
            'library.loans.view',
            'library.loans.request',
            'library.reservations.view',
            'library.reservations.create',
            'library.incidents.view',
            'library.incidents.create',
        ];
    }

    private function studentLibraryPermissions(): array
    {
        return [
            'library.collections.view',
            'library.authors.view',
            'library.publishers.view',
            'library.books.view',
            'library.book-files.view',
            'library.book-files.download',
            'library.loans.view',
            'library.loans.request',
            'library.reservations.view',
            'library.reservations.create',
        ];
    }

    private function parentLibraryFinancePermissions(): array
    {
        return [
            'library.collections.view',
            'library.authors.view',
            'library.publishers.view',
            'library.books.view',
            'library.book-files.view',
            'library.book-files.download',
            'library.loans.view',
            'library.reservations.view',
            'finance.invoices.view',
            'finance.payments.view',
            'finance.payments.create', // Parents can make payments for their children
        ];
    }

    private function guestLibraryPermissions(): array
    {
        return [
            'library.collections.view',
            'library.authors.view',
            'library.publishers.view',
            'library.books.view',
            'library.book-files.view',
            'library.book-files.download',
        ];
    }

    private function formAdminPermissions(): array
    {
        return [
            'form_templates.view',
            'form_templates.create',
            'form_templates.edit',
            'form_templates.delete',
            'form_templates.publish',
            'form_templates.archive',
            'form_templates.duplicate',
            'form_instances.view',
            'form_instances.create',
            'form_instances.edit',
            'form_instances.delete',
            'form_instances.submit',
            'form_instances.approve',
            'form_instances.reject',
            'form_instances.review',
            'form_submissions.view',
            'form_submissions.create',
            'form_submissions.edit',
            'form_submissions.delete',
            'form_submissions.export',
            'form_analytics.view',
            'form_workflow.manage',
            'form_compliance.view',
        ];
    }

    private function formDesignerPermissions(): array
    {
        return [
            'form_templates.view',
            'form_templates.create',
            'form_templates.edit',
            'form_templates.delete',
            'form_templates.publish',
            'form_templates.archive',
            'form_templates.duplicate',
            'form_instances.view',
            'form_submissions.view',
        ];
    }

    private function formReviewerPermissions(): array
    {
        return [
            'form_templates.view',
            'form_instances.view',
            'form_instances.create',
            'form_instances.edit',
            'form_instances.delete',
            'form_instances.submit',
            'form_instances.approve',
            'form_instances.reject',
            'form_instances.review',
            'form_submissions.view',
            'form_submissions.create',
            'form_submissions.edit',
            'form_submissions.delete',
            'form_submissions.export',
            'form_analytics.view',
        ];
    }

    private function formSubmitterPermissions(): array
    {
        return [
            'form_templates.view',
            'form_instances.create',
            'form_instances.edit',
            'form_instances.submit',
            'form_submissions.create',
            'form_submissions.view',
        ];
    }

    private function formAnalystPermissions(): array
    {
        return [
            'form_templates.view',
            'form_instances.view',
            'form_submissions.view',
            'form_analytics.view',
            'form_analytics.export',
            'form_submissions.export',
        ];
    }

    private function projectManagerFormPermissions(): array
    {
        return [
            'form_templates.view',
            'form_instances.view',
            'form_instances.create',
            'form_instances.edit',
            'form_instances.delete',
            'form_instances.submit',
            'form_instances.approve',
            'form_instances.reject',
            'form_instances.review',
            'form_submissions.view',
            'form_submissions.create',
            'form_submissions.edit',
            'form_submissions.delete',
            'form_submissions.export',
            'form_analytics.view',
            'form_workflow.approve',
            'form_workflow.reject',
        ];
    }

    private function teamMemberFormPermissions(): array
    {
        return [
            'form_templates.view',
            'form_instances.create',
            'form_instances.edit',
            'form_instances.submit',
            'form_submissions.create',
            'form_submissions.view',
        ];
    }

    private function viewerFormPermissions(): array
    {
        return [
            'form_templates.view',
            'form_instances.view',
            'form_submissions.view',
        ];
    }

    private function academicAdministratorPermissions(): array
    {
        return [
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
        ];
    }

    private function academicCoordinatorPermissions(): array
    {
        return [
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
        ];
    }

    private function academicTeacherPermissions(): array
    {
        return [
            'academic.subjects.view',
            'academic.classes.view',
            'academic.grade-entries.view',
            'academic.grade-entries.create',
            'academic.grade-entries.edit',
            'academic.grade-entries.delete',
            'academic.analytics.view',
        ];
    }

    private function academicStudentPermissions(): array
    {
        return [
            'academic.subjects.view',
            'academic.classes.view',
            'academic.grade-entries.view',
        ];
    }

    private function assessmentAdministratorPermissions(): array
    {
        return [
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
        ];
    }

    private function assessmentCoordinatorPermissions(): array
    {
        return [
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
        ];
    }

    private function assessmentTeacherPermissions(): array
    {
        return [
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
        ];
    }

    private function assessmentStudentPermissions(): array
    {
        return [
            'assessment.assessments.view',
            'assessment.grades.view',
            'assessment.resources.view',
            'assessment.resources.download',
            'assessment.grade-reviews.create',
        ];
    }

    private function assessmentParentPermissions(): array
    {
        return [
            'assessment.assessments.view',
            'assessment.grades.view',
            'assessment.analytics.view',
            'assessment.reports.view',
        ];
    }

    private function transportAdministratorPermissions(): array
    {
        return [
            'transport.routes.view',
            'transport.routes.create',
            'transport.routes.edit',
            'transport.routes.delete',
            'transport.routes.manage',
            'transport.vehicles.view',
            'transport.vehicles.create',
            'transport.vehicles.edit',
            'transport.vehicles.delete',
            'transport.vehicles.manage',
            'transport.drivers.view',
            'transport.drivers.create',
            'transport.drivers.edit',
            'transport.drivers.delete',
            'transport.drivers.manage',
            'transport.subscriptions.view',
            'transport.subscriptions.create',
            'transport.subscriptions.edit',
            'transport.subscriptions.delete',
            'transport.subscriptions.manage',
            'transport.students.view',
            'transport.students.assign',
            'transport.students.remove',
            'transport.students.manage',
            'transport.reports.view',
            'transport.reports.export',
            'transport.reports.analytics',
            'transport.notifications.view',
            'transport.notifications.send',
            'transport.notifications.manage',
            'transport.settings.view',
            'transport.settings.edit',
            'transport.settings.manage',
            'view-transport',
            'create-transport',
            'edit-transport',
            'delete-transport',
            'view-transport-subscriptions',
            'create-transport-subscriptions',
            'edit-transport-subscriptions',
            'delete-transport-subscriptions',
        ];
    }

    private function transportManagerPermissions(): array
    {
        return [
            'transport.routes.view',
            'transport.routes.create',
            'transport.routes.edit',
            'transport.vehicles.view',
            'transport.vehicles.create',
            'transport.vehicles.edit',
            'transport.drivers.view',
            'transport.drivers.create',
            'transport.drivers.edit',
            'transport.subscriptions.view',
            'transport.subscriptions.create',
            'transport.subscriptions.edit',
            'transport.students.view',
            'transport.students.assign',
            'transport.students.remove',
            'transport.reports.view',
            'transport.reports.export',
            'transport.notifications.view',
            'transport.notifications.send',
            'transport.settings.view',
            'view-transport',
            'create-transport',
            'edit-transport',
            'view-transport-subscriptions',
            'create-transport-subscriptions',
            'edit-transport-subscriptions',
        ];
    }

    private function transportCoordinatorPermissions(): array
    {
        return [
            'transport.routes.view',
            'transport.routes.create',
            'transport.routes.edit',
            'transport.vehicles.view',
            'transport.vehicles.create',
            'transport.vehicles.edit',
            'transport.drivers.view',
            'transport.drivers.create',
            'transport.drivers.edit',
            'transport.subscriptions.view',
            'transport.subscriptions.create',
            'transport.subscriptions.edit',
            'transport.students.view',
            'transport.students.assign',
            'transport.students.remove',
            'transport.reports.view',
            'transport.reports.export',
            'transport.notifications.view',
            'transport.notifications.send',
            'view-transport',
            'create-transport',
            'edit-transport',
            'view-transport-subscriptions',
            'create-transport-subscriptions',
            'edit-transport-subscriptions',
        ];
    }

    private function transportDriverPermissions(): array
    {
        return [
            'transport.routes.view',
            'transport.vehicles.view',
            'transport.students.view',
            'transport.notifications.view',
            'view-transport',
        ];
    }

    private function transportParentPermissions(): array
    {
        return [
            'transport.routes.view',
            'transport.subscriptions.view',
            'transport.students.view',
            'transport.notifications.view',
            'view-transport',
            'view-transport-subscriptions',
        ];
    }

    private function schoolPermissions(): array
    {
        return [
            'schools.view',
            'schools.create',
            'schools.edit',
            'schools.delete',
            'schools.statistics',
        ];
    }

    private function schoolViewPermissions(): array
    {
        return [
            'schools.view',
        ];
    }

    private function schoolOwnerPermissions(): array
    {
        return array_merge(
            $this->schoolPermissions(),
            $this->schoolAdminPermissions(),
            [
                'tenants.manage_settings',
                'tenants.view',
                'schools.view_all',
            ]
        );
    }

    private function schoolAdminPermissions(): array
    {
        return array_merge(
            $this->libraryAdminPermissions(),
            $this->financeAdminPermissions(),
            $this->formAdminPermissions(),
            $this->academicAdministratorPermissions(),
            $this->assessmentAdministratorPermissions(),
            $this->transportAdministratorPermissions(),
            $this->schoolPermissions(),
            [
                'users.view',
                'users.manage',
                'users.create',
                'users.edit',
                'users.delete',
                'teams.view',
                'teams.manage',
                'teams.invite',
                'teams.remove',
            ]
        );
    }

    private function accountantPermissions(): array
    {
        return [
            'finance.manage',
            'finance.accounts.view',
            'finance.accounts.create',
            'finance.accounts.update',
            'finance.accounts.delete',
            'finance.invoices.view',
            'finance.invoices.create',
            'finance.invoices.update',
            'finance.invoices.delete',
            'finance.invoices.issue',
            'finance.payments.view',
            'finance.payments.create',
            'finance.fees.view',
            'finance.fees.create',
            'finance.fees.update',
            'finance.fees.delete',
            'finance.fees.apply',
            'finance.expenses.view',
            'finance.expenses.create',
            'finance.expenses.update',
            'finance.expenses.delete',
            'finance.reports.view',
            'schools.view',
        ];
    }

    private function secretaryPermissions(): array
    {
        return [
            'academic.classes.view',
            'academic.classes.enroll',
            'academic.teachers.view',
            'academic.subjects.view',
            'library.collections.view',
            'library.authors.view',
            'library.publishers.view',
            'library.books.view',
            'library.book-files.view',
            'library.book-files.download',
            'schools.view',
        ];
    }
}

