<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subscription\SubscriptionPackage;

class SubscriptionPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Package 1: Starter Tier
        SubscriptionPackage::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Starter',
                'description' => 'Perfeito para pequenos negócios',
                'duration_days' => 30,
                'price' => 495.00,
                'status' => 'active',
                'features' => [
                    'max_invoices' => 50,
                    'max_quotes' => 30,
                    'max_expenses' => 50,
                    'max_users' => 2,
                    'max_products' => 100,
                    'max_customers' => null, // Unlimited
                    'max_employees' => 0, // No HR module
                    'max_storage_mb' => 500, // 500MB
                    'max_recurring_items' => 5,
                    'history_months' => 6,
                    'hr_module' => false,
                    'advanced_reports' => false,
                    'api_access' => false,
                    'integrations' => false,
                    'ai_features' => false,
                    'inventory_management' => false,
                    'multi_currency' => false,
                    'white_label' => false,
                    'custom_templates' => 0,
                    'custom_widgets' => 0,
                ]
            ]
        );

        // Package 2: Professional Tier
        SubscriptionPackage::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Professional',
                'description' => 'Para negócios em crescimento',
                'duration_days' => 30,
                'price' => 995.00,
                'status' => 'active',
                'features' => [
                    'max_invoices' => 200,
                    'max_quotes' => 150,
                    'max_expenses' => 200,
                    'max_users' => 5,
                    'max_products' => 500,
                    'max_customers' => null, // Unlimited
                    'max_employees' => null, // Unlimited (HR module available)
                    'max_storage_mb' => 5120, // 5GB
                    'max_recurring_items' => null, // Unlimited
                    'max_api_requests_per_day' => 1000,
                    'max_ai_queries_per_month' => 10,
                    'history_months' => 24, // 2 years
                    'hr_module' => true,
                    'advanced_reports' => true,
                    'api_access' => true,
                    'integrations' => true, // Standard integrations
                    'ai_features' => true,
                    'inventory_management' => true,
                    'multi_currency' => false,
                    'white_label' => false,
                    'custom_templates' => 3,
                    'custom_widgets' => null, // Unlimited
                ]
            ]
        );

        // Package 3: Business Tier
        SubscriptionPackage::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'Business',
                'description' => 'A solução definitiva para seu negócio',
                'duration_days' => 30,
                'price' => 1495.00,
                'status' => 'active',
                'features' => [
                    'max_invoices' => null, // Unlimited
                    'max_quotes' => null, // Unlimited
                    'max_expenses' => null, // Unlimited
                    'max_users' => 15,
                    'max_products' => null, // Unlimited
                    'max_customers' => null, // Unlimited
                    'max_employees' => null, // Unlimited
                    'max_storage_mb' => 51200, // 50GB
                    'max_recurring_items' => null, // Unlimited
                    'max_api_requests_per_day' => 10000,
                    'max_ai_queries_per_month' => null, // Unlimited
                    'history_months' => null, // Unlimited
                    'hr_module' => true,
                    'advanced_reports' => true,
                    'api_access' => true,
                    'integrations' => true, // All integrations + custom
                    'ai_features' => true,
                    'inventory_management' => true,
                    'advanced_inventory' => true,
                    'multi_currency' => true,
                    'white_label' => true,
                    'custom_templates' => null, // Unlimited
                    'custom_widgets' => 10,
                ]
            ]
        );

        // Package 4: Free Tier
        SubscriptionPackage::updateOrCreate(
            ['id' => 4],
            [
                'name' => 'Free',
                'description' => 'Plano gratuito com recursos básicos',
                'duration_days' => 30,
                'price' => 0.00,
                'status' => 'active',
                'features' => [
                    'max_invoices' => 10,
                    'max_quotes' => 5,
                    'max_expenses' => 10,
                    'max_users' => 1,
                    'max_products' => 15,
                    'max_customers' => 5,
                    'max_employees' => 0, // No HR module
                    'max_storage_mb' => 100, // 100MB
                    'max_recurring_items' => 0,
                    'history_months' => 3,
                    'hr_module' => false,
                    'advanced_reports' => false,
                    'api_access' => false,
                    'integrations' => false,
                    'ai_features' => false,
                    'inventory_management' => false,
                    'multi_currency' => false,
                    'white_label' => false,
                    'custom_templates' => 0,
                    'custom_widgets' => 0,
                ]
            ]
        );
    }
}

