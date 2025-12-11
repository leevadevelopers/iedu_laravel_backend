<?php

namespace Database\Seeders;

use Database\Seeders\Permissions\AcademicPermissionsSeeder;
use Database\Seeders\Permissions\AssessmentPermissionsSeeder;
use Database\Seeders\Permissions\FinancialPermissionsSeeder;
use Database\Seeders\Permissions\FormPermissionSeeder;
use Database\Seeders\Permissions\LibraryPermissionsSeeder;
use Database\Seeders\Permissions\TransportPermissionsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AllPermissionsSeeder::class,
            FormPermissionSeeder::class,
            AcademicPermissionsSeeder::class,
            AssessmentPermissionsSeeder::class,
            TransportPermissionsSeeder::class,
            LibraryPermissionsSeeder::class,
            FinancialPermissionsSeeder::class,
            RolesSeeder::class,
            SubscriptionPackageSeeder::class, // Seed subscription packages
            TenantSeeder::class, // Create tenant before users
            UserSeeder::class, // Users will be assigned to tenant
            SchoolSeeder::class, // Create school and associate users
        ]);
    }
}


