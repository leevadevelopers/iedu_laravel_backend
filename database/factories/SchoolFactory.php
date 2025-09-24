<?php

namespace Database\Factories;

use App\Models\V1\SIS\School\School;
use App\Models\Settings\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\V1\SIS\School\School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'school_code' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{4}'),
            'official_name' => $this->faker->company() . ' School',
            'display_name' => $this->faker->company() . ' School',
            'short_name' => $this->faker->lexify('???'),
            'school_type' => $this->faker->randomElement([
                'elementary', 'middle', 'high', 'k12', 'preschool', 'kindergarten',
                'primary', 'secondary', 'university', 'college', 'vocational'
            ]),
            'educational_levels' => $this->faker->randomElements([
                'preschool', 'kindergarten', 'elementary', 'middle', 'high', 'university'
            ], $this->faker->numberBetween(1, 3)),
            'grade_range_min' => $this->faker->randomElement(['PK', 'K', '1', '2', '3', '4', '5']),
            'grade_range_max' => $this->faker->randomElement(['5', '6', '8', '9', '12', '12+']),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->optional()->url(),
            'address_json' => [
                'street' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->state(),
                'postal_code' => $this->faker->postcode(),
                'country' => $this->faker->country(),
            ],
            'country_code' => $this->faker->countryCode(),
            'state_province' => $this->faker->state(),
            'city' => $this->faker->city(),
            'timezone' => $this->faker->timezone(),
            'ministry_education_code' => $this->faker->optional()->regexify('[A-Z]{2}[0-9]{6}'),
            'accreditation_status' => $this->faker->randomElement([
                'accredited', 'provisionally_accredited', 'not_accredited', 'pending'
            ]),
            'academic_calendar_type' => $this->faker->randomElement([
                'semester', 'trimester', 'quarter', 'year_round'
            ]),
            'academic_year_start_month' => $this->faker->numberBetween(1, 12),
            'grading_system' => $this->faker->randomElement([
                'letter_grade', 'percentage', 'gpa', 'pass_fail', 'custom'
            ]),
            'attendance_tracking_level' => $this->faker->randomElement([
                'daily', 'period', 'class', 'session'
            ]),
            'educational_philosophy' => $this->faker->optional()->sentence(),
            'language_instruction' => $this->faker->randomElements([
                'english', 'spanish', 'french', 'mandarin', 'arabic'
            ], $this->faker->numberBetween(1, 3)),
            'religious_affiliation' => $this->faker->optional()->randomElement([
                'christian', 'catholic', 'protestant', 'jewish', 'islamic', 'buddhist', 'hindu', 'none'
            ]),
            'student_capacity' => $this->faker->numberBetween(100, 2000),
            'current_enrollment' => $this->faker->numberBetween(50, 1500),
            'staff_count' => $this->faker->numberBetween(10, 200),
            'subscription_plan' => $this->faker->randomElement([
                'basic', 'standard', 'premium', 'enterprise'
            ]),
            'feature_flags' => [
                'attendance_tracking' => $this->faker->boolean(80),
                'grade_management' => $this->faker->boolean(90),
                'parent_portal' => $this->faker->boolean(70),
                'student_portal' => $this->faker->boolean(60),
                'transportation' => $this->faker->boolean(50),
                'cafeteria' => $this->faker->boolean(60),
                'library' => $this->faker->boolean(70),
            ],
            'integration_settings' => [
                'sso_enabled' => $this->faker->boolean(30),
                'ldap_enabled' => $this->faker->boolean(20),
                'api_enabled' => $this->faker->boolean(60),
            ],
            'branding_configuration' => [
                'primary_color' => $this->faker->hexColor(),
                'secondary_color' => $this->faker->hexColor(),
                'logo_url' => $this->faker->optional()->imageUrl(),
                'favicon_url' => $this->faker->optional()->imageUrl(),
            ],
            'status' => $this->faker->randomElement(['active', 'setup', 'inactive', 'suspended']),
            'established_date' => $this->faker->optional()->dateTimeBetween('-50 years', '-1 year'),
            'onboarding_completed_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'trial_ends_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }

    /**
     * Indicate that the school is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the school is in setup mode.
     */
    public function setup(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'setup',
            'onboarding_completed_at' => null,
        ]);
    }

    /**
     * Indicate that the school is on trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    /**
     * Indicate that the school is elementary level.
     */
    public function elementary(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_type' => 'elementary',
            'educational_levels' => ['elementary'],
            'grade_range_min' => 'K',
            'grade_range_max' => '5',
        ]);
    }

    /**
     * Indicate that the school is high school level.
     */
    public function highSchool(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_type' => 'high',
            'educational_levels' => ['high'],
            'grade_range_min' => '9',
            'grade_range_max' => '12',
        ]);
    }

    /**
     * Indicate that the school is K-12.
     */
    public function k12(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_type' => 'k12',
            'educational_levels' => ['elementary', 'middle', 'high'],
            'grade_range_min' => 'K',
            'grade_range_max' => '12',
        ]);
    }
}
