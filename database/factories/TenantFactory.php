<?php

namespace Database\Factories;

use App\Models\Settings\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Settings\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'domain' => $this->faker->optional()->domainName(),
            'database' => $this->faker->optional()->word(),
            'settings' => [
                'timezone' => $this->faker->timezone(),
                'currency' => $this->faker->currencyCode(),
                'date_format' => $this->faker->randomElement(['Y-m-d', 'm/d/Y', 'd/m/Y']),
                'time_format' => $this->faker->randomElement(['12', '24']),
                'language' => $this->faker->randomElement(['en', 'es', 'fr', 'de']),
                'features' => $this->faker->randomElements([
                    'forms', 'workflows', 'notifications', 'reports', 'analytics',
                    'integrations', 'api_access', 'custom_fields', 'templates'
                ], $this->faker->numberBetween(3, 7)),
                'branding' => [
                    'primary_color' => $this->faker->hexColor(),
                    'secondary_color' => $this->faker->hexColor(),
                    'logo_url' => $this->faker->optional()->imageUrl(),
                ],
                'notifications' => [
                    'email_enabled' => $this->faker->boolean(80),
                    'sms_enabled' => $this->faker->boolean(30),
                    'push_enabled' => $this->faker->boolean(60),
                ],
                'security' => [
                    'password_policy' => 'standard',
                    'two_factor_enabled' => $this->faker->boolean(40),
                    'session_timeout' => $this->faker->numberBetween(30, 480),
                ],
            ],
            'is_active' => $this->faker->boolean(90),
            'owner_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the tenant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tenant has a specific domain.
     */
    public function withDomain(string $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'domain' => $domain,
        ]);
    }

    /**
     * Indicate that the tenant has specific features enabled.
     */
    public function withFeatures(array $features): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'features' => $features,
            ]),
        ]);
    }

    /**
     * Indicate that the tenant has custom branding.
     */
    public function withBranding(array $branding): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'branding' => array_merge($attributes['settings']['branding'] ?? [], $branding),
            ]),
        ]);
    }
}
