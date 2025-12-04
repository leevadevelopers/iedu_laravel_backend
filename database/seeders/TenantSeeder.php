<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if tenant with ID 1 already exists
        $tenant = Tenant::find(1);

        if (!$tenant) {
            // If tenant ID 1 doesn't exist, create it
            // Use DB::table to insert with specific ID
            DB::table('tenants')->insert([
                'id' => 1,
                'name' => 'Default Tenant',
                'slug' => 'default',
                'domain' => 'localhost',
                'database' => null,
                'is_active' => true,
                'settings' => json_encode([
                    'theme' => 'light',
                    'language' => 'pt',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $tenant = Tenant::find(1);
            $this->command->info('Created Tenant ID 1: ' . $tenant->name);
        } else {
            // Update existing tenant to ensure it has correct values
            $tenant->update([
                'name' => 'Default Tenant',
                'slug' => 'default',
                'domain' => 'localhost',
                'is_active' => true,
                'settings' => [
                    'theme' => 'light',
                    'language' => 'pt',
                ],
            ]);
            $this->command->info('Verified Tenant ID 1: ' . $tenant->name);
        }
    }
}

