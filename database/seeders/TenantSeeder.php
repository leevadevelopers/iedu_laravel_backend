<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Settings\Tenant;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme-corp',
                'domain' => 'acme.example.com',
                'database' => 'acme_db',
                'settings' => json_encode([
                    'timezone' => 'UTC',
                    'locale' => 'en',
                    'currency' => 'USD'
                ]),
                'is_active' => true,
                'created_by' => 1
            ],
            [
                'name' => 'TechStart Inc',
                'slug' => 'techstart',
                'domain' => 'techstart.example.com',
                'database' => 'techstart_db',
                'settings' => json_encode([
                    'timezone' => 'America/New_York',
                    'locale' => 'en',
                    'currency' => 'USD'
                ]),
                'is_active' => true,
                'created_by' => 1
            ],
            [
                'name' => 'Global Solutions Ltd',
                'slug' => 'global-solutions',
                'domain' => 'global.example.com',
                'database' => 'global_db',
                'settings' => json_encode([
                    'timezone' => 'Europe/London',
                    'locale' => 'en',
                    'currency' => 'GBP'
                ]),
                'is_active' => true,
                'created_by' => 1
            ],
            [
                'name' => 'Innovation Hub',
                'slug' => 'innovation-hub',
                'domain' => 'innovation.example.com',
                'database' => 'innovation_db',
                'settings' => json_encode([
                    'timezone' => 'Asia/Tokyo',
                    'locale' => 'en',
                    'currency' => 'JPY'
                ]),
                'is_active' => true,
                'created_by' => 1
            ]
        ];

        foreach ($tenants as $tenantData) {
            Tenant::create($tenantData);
        }
    }
} 