<?php

namespace Database\Seeders\Financial;

use App\Models\V1\Financial\FinancialAccount;
use App\Models\V1\Financial\Fee;
use Illuminate\Database\Seeder;

class FinancialSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1; // Change based on your tenant

        // Create Financial Accounts
        $accounts = [
            [
                'tenant_id' => $tenantId,
                'name' => 'Cash Account',
                'code' => 'ACC-CASH-001',
                'type' => 'asset',
                'balance' => 0,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Accounts Receivable',
                'code' => 'ACC-AR-001',
                'type' => 'asset',
                'balance' => 0,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Student Fees',
                'code' => 'ACC-REV-001',
                'type' => 'income',
                'balance' => 0,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Operating Expenses',
                'code' => 'ACC-EXP-001',
                'type' => 'expense',
                'balance' => 0,
            ],
        ];

        foreach ($accounts as $account) {
            FinancialAccount::create($account);
        }

        // Create Fees
        $fees = [
            [
                'tenant_id' => $tenantId,
                'name' => 'Monthly Tuition',
                'code' => 'FEE-TUITION-001',
                'description' => 'Monthly tuition fee',
                'amount' => 5000.00,
                'recurring' => true,
                'frequency' => 'monthly',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Library Late Fee',
                'code' => 'FEE-LIBRARY-001',
                'description' => 'Fee for late book returns',
                'amount' => 10.00,
                'recurring' => false,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Book Damage Fee',
                'code' => 'FEE-DAMAGE-001',
                'description' => 'Fee for damaged library books',
                'amount' => 100.00,
                'recurring' => false,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Book Loss Fee',
                'code' => 'FEE-LOSS-001',
                'description' => 'Fee for lost library books',
                'amount' => 500.00,
                'recurring' => false,
                'is_active' => true,
            ],
        ];

        foreach ($fees as $fee) {
            Fee::create($fee);
        }

        $this->command->info('Financial data seeded successfully!');
    }
}
