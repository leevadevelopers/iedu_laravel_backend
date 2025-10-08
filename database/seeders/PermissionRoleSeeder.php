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

        $this->createRoles();
        $this->assignPermissionsToRoles();
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
                'name' => 'tenant_admin',
                'display_name' => 'Tenant Administrator',
                'description' => 'Administrative access within tenant scope',
                'is_system' => false,
            ],
            [
                'name' => 'librarian',
                'display_name' => 'Librarian',
                'description' => 'Library management access',
                'is_system' => false,
            ],
            [
                'name' => 'finance_manager',
                'display_name' => 'Finance Manager',
                'description' => 'Financial management access',
                'is_system' => false,
            ],
            [
                'name' => 'teacher',
                'display_name' => 'Teacher',
                'description' => 'Teacher access to the system',
                'is_system' => false,
            ],
            [
                'name' => 'student',
                'display_name' => 'Student',
                'description' => 'Student access to the system',
                'is_system' => false,
            ],
            [
                'name' => 'parent',
                'display_name' => 'Parent',
                'description' => 'Parent access to the system',
                'is_system' => false,
            ],
            [
                'name' => 'guest',
                'display_name' => 'Guest',
                'description' => 'Guest access to the system',
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

        // Admin - Most permissions except super admin functions
        $admin = Role::where('name', 'admin')->first();
        $admin->givePermissionTo([
            'library.manage',
            'finance.manage',
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
        ]);

        // Tenant Admin - All tenant permissions
        $tenantAdmin = Role::where('name', 'tenant_admin')->first();
        $tenantAdmin->givePermissionTo([
            'library.manage',
            'finance.manage',
        ]);

        // Librarian - Library management
        $librarian = Role::where('name', 'librarian')->first();
        $librarian->givePermissionTo([
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
        ]);

        // Finance Manager - Financial management
        $financeManager = Role::where('name', 'finance_manager')->first();
        $financeManager->givePermissionTo([
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
        ]);

        // Teacher - Limited access
        $teacher = Role::where('name', 'teacher')->first();
        $teacher->givePermissionTo([
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
        ]);

        // Student - Basic access
        $student = Role::where('name', 'student')->first();
        $student->givePermissionTo([
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
        ]);

        // Parent - View only
        $parent = Role::where('name', 'parent')->first();
        $parent->givePermissionTo([
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
        ]);

        // Guest - Minimal view access
        $guest = Role::where('name', 'guest')->first();
        $guest->givePermissionTo([
            'library.collections.view',
            'library.authors.view',
            'library.publishers.view',
            'library.books.view',
            'library.book-files.view',
            'library.book-files.download',
        ]);

        $this->command->info('Roles and permissions assigned successfully!');
    }
}
