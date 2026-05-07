<?php

namespace Database\Seeders;

use Database\Seeders\permissions\AcademicPermissionsSeeder;
use Database\Seeders\permissions\AssessmentPermissionsSeeder;
use Database\Seeders\permissions\FinancialPermissionsSeeder;
use Database\Seeders\permissions\FormPermissionSeeder;
use Database\Seeders\permissions\LibraryPermissionsSeeder;
use Database\Seeders\permissions\TransportPermissionsSeeder;
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


