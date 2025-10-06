<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class FinancialPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $financialPermissions = [
            // Financial Accounts
            'finance.accounts.view',
            'finance.accounts.create',
            'finance.accounts.update',
            'finance.accounts.delete',

            // Invoices
            'finance.invoices.view',
            'finance.invoices.create',
            'finance.invoices.update',
            'finance.invoices.delete',
            'finance.invoices.issue',

            // Payments
            'finance.payments.view',
            'finance.payments.create',

            // Fees
            'finance.fees.view',
            'finance.fees.create',
            'finance.fees.update',
            'finance.fees.delete',
            'finance.fees.apply',

            // Expenses
            'finance.expenses.view',
            'finance.expenses.create',
            'finance.expenses.update',
            'finance.expenses.delete',

            // Reports
            'finance.reports.view',

            // General
            'finance.manage',
        ];

        foreach ($financialPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $this->command->info('Financial permissions created successfully!');
    }
}
