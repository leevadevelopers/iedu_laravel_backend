<?php

namespace App\Listeners;

use App\Events\TenantCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProvisionTenantDefaults implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function handle(TenantCreated $event): void
    {
        $tenant = $event->tenant->fresh();
        if (!$tenant) {
            return;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        if (($settings['onboarding_defaults_version'] ?? null) === 1) {
            return;
        }

        $countryCode = strtoupper((string)($settings['country_code'] ?? 'MZ'));

        $defaultsByCountry = [
            'MZ' => ['currency' => 'MZN', 'language' => 'pt-MZ', 'timezone' => 'Africa/Maputo'],
            'AO' => ['currency' => 'AOA', 'language' => 'pt', 'timezone' => 'Africa/Luanda'],
            'BR' => ['currency' => 'BRL', 'language' => 'pt-BR', 'timezone' => 'America/Sao_Paulo'],
            'PT' => ['currency' => 'EUR', 'language' => 'pt-PT', 'timezone' => 'Europe/Lisbon'],
        ];

        $countryDefaults = $defaultsByCountry[$countryCode] ?? [
            'currency' => 'USD',
            'language' => 'en',
            'timezone' => 'UTC',
        ];

        $tenant->update([
            'settings' => array_merge(
                $settings,
                $countryDefaults,
                [
                    'country_code' => $countryCode,
                    'onboarding_defaults_version' => 1,
                    'onboarding_provisioned_at' => now()->toISOString(),
                ]
            ),
        ]);

        Log::info('Tenant defaults provisioned', [
            'tenant_id' => $tenant->id,
            'country_code' => $countryCode,
        ]);
    }
}

