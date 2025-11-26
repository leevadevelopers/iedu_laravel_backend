<?php

namespace Database\Seeders;

use Database\Seeders\Library\LibrarySeeder;
use Database\Seeders\Financial\FinancialSeeder;
use Database\Seeders\Permissions\LibraryPermissionsSeeder;
use Database\Seeders\Permissions\FinancialPermissionsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AllPermissionsSeeder::class,
            LibraryPermissionsSeeder::class,
            FinancialPermissionsSeeder::class,
            PermissionRoleSeeder::class,
            UserSeeder::class,
            TenantSeeder::class,
            SchoolSeeder::class,
            SchoolUserSeeder::class,
            SchoolFormTemplatesSeeder::class,
            LibrarySeeder::class,
            FinancialSeeder::class,
        ]);

        $this->command->info('✅ Database seeded successfully!');
    }
}
