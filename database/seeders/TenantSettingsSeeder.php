<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\DB;

class TenantSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing tenants
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Default branding settings
            $defaultBranding = [
                'logo' => '/assets/images/logo/default-logo.png',
                'favicon' => '/assets/images/favicon/default-favicon.ico',
                'primaryColor' => '#3b82f6',
                'secondaryColor' => '#10b981',
                'accentColor' => '#8b5cf6',
                'companyName' => $tenant->name,
                'shortName' => substr($tenant->name, 0, 3),
                'description' => 'Organização ' . $tenant->name,
                'website' => 'https://' . ($tenant->domain ?? 'example.com'),
                'email' => 'contact@' . ($tenant->domain ?? 'example.com'),
                'phone' => '+258 868 875 269',
                'address' => 'Maputo, Moçambique',
            ];
            
            // Default UI settings
            $defaultUI = [
                'theme' => 'light',
                'layout' => 'classy',
                'density' => 'standard',
                'animations' => true,
                'showBreadcrumbs' => true,
                'sidebarCollapsed' => false,
                'showUserMenu' => true,
                'showNotifications' => true,
                'showSearch' => true,
                'showHelp' => true,
                'showSettings' => true,
                'maxNotifications' => 5,
                'notificationTimeout' => 5000,
            ];
            
            // Default app settings
            $defaultApp = [
                'timezone' => 'Africa/Maputo',
                'dateFormat' => 'DD/MM/YYYY',
                'timeFormat' => 'HH:mm:ss',
                'currency' => 'MZN',
                'language' => 'pt',
                'locale' => 'pt-MZ',
            ];
            
            // Handle existing settings - ensure it's an array
            $currentSettings = $tenant->settings ?? [];
            if (is_string($currentSettings)) {
                $currentSettings = json_decode($currentSettings, true) ?? [];
            }
            if (!is_array($currentSettings)) {
                $currentSettings = [];
            }
            
            // Merge settings with proper array handling
            $mergedSettings = array_merge($currentSettings, [
                'branding' => array_merge(
                    is_array($currentSettings['branding'] ?? null) ? $currentSettings['branding'] : [], 
                    $defaultBranding
                ),
                'ui' => array_merge(
                    is_array($currentSettings['ui'] ?? null) ? $currentSettings['ui'] : [], 
                    $defaultUI
                ),
                'app' => array_merge(
                    is_array($currentSettings['app'] ?? null) ? $currentSettings['app'] : [], 
                    $defaultApp
                ),
            ]);
            
            // Update tenant with new settings
            $tenant->update(['settings' => $mergedSettings]);
            
            $this->command->info("Updated settings for tenant: {$tenant->name}");
        }
        
        $this->command->info('Tenant settings seeding completed successfully!');
    }
}
