<?php

namespace Database\Seeders;

use Database\Seeders\Permissions\AcademicPermissionsSeeder;
use Database\Seeders\Permissions\AssessmentPermissionsSeeder;
use Database\Seeders\Permissions\LibraryPermissionsSeeder;
use Database\Seeders\Permissions\FinancialPermissionsSeeder;
use Database\Seeders\Permissions\TransportPermissionsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FinancialPermissionsSeeder::class,
            LibraryPermissionsSeeder::class,
            AcademicPermissionsSeeder::class,
            AllPermissionsSeeder::class,
            FormPermissionSeeder::class,
            AssessmentPermissionsSeeder::class,
            TransportPermissionsSeeder::class,

            PermissionRoleSeeder::class,
            UserSeeder::class,
        ]);

        $this->command->info('✅ Database seeded successfully!');
    }
}
