<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\SIS\School\School;
use App\Models\Settings\Tenant;
use App\Models\User;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->createSchoolsForTenant($tenant);
        }
    }

    private function createSchoolsForTenant(Tenant $tenant): void
    {
        // Create a default school for each tenant
        $school = School::create([
            'tenant_id' => $tenant->id,
            'school_code' => 'SCH-' . strtoupper(substr($tenant->slug, 0, 3)) . '-001',
            'official_name' => $tenant->name . ' School',
            'display_name' => $tenant->name . ' School',
            'short_name' => substr($tenant->name, 0, 10) . ' School',
            'school_type' => 'private',
            'educational_levels' => ['elementary', 'middle', 'high'],
            'grade_range_min' => 'K',
            'grade_range_max' => '12',
            'email' => 'info@' . ($tenant->domain ?? 'example.com'),
            'phone' => '+258 868 875 269',
            'website' => 'https://' . ($tenant->domain ?? 'example.com'),
            'address_json' => [
                'street' => '123 Education Street',
                'city' => 'Maputo',
                'state' => 'Maputo',
                'postal_code' => '1100',
                'country' => 'MZ'
            ],
            'country_code' => 'MZ',
            'state_province' => 'Maputo',
            'city' => 'Maputo',
            'timezone' => 'Africa/Maputo',
            'ministry_education_code' => 'MOE-' . strtoupper(substr($tenant->slug, 0, 3)),
            'accreditation_status' => 'accredited',
            'academic_calendar_type' => 'semester',
            'academic_year_start_month' => 8,
            'grading_system' => 'traditional_letter',
            'attendance_tracking_level' => 'daily',
            'educational_philosophy' => 'Providing quality education for all students',
            'language_instruction' => ['Portuguese', 'English'],
            'religious_affiliation' => null,
            'student_capacity' => 1000,
            'current_enrollment' => 0,
            'staff_count' => 0,
            'subscription_plan' => 'basic',
            'feature_flags' => [],
            'integration_settings' => [],
            'branding_configuration' => [],
            'status' => 'active',
            'established_date' => now()->subYears(5),
            'onboarding_completed_at' => now(),
            'trial_ends_at' => null,
        ]);

        // Associate all users from this tenant with the school
        $users = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenants.id', $tenant->id);
        })->get();

        foreach ($users as $user) {
            // Check if user is already associated with this school
            if (!$user->schools()->where('schools.id', $school->id)->exists()) {
                $user->schools()->attach($school->id, [
                    'role' => $this->getUserRoleForSchool($user, $tenant),
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => null,
                    'permissions' => null,
                ]);
            }
        }

        $this->command->info("Created school '{$school->official_name}' for tenant '{$tenant->name}' and associated {$users->count()} users.");
    }

    private function getUserRoleForSchool(User $user, Tenant $tenant): string
    {
        // Get user's role in the tenant
        $tenantUser = $user->tenants()->where('tenants.id', $tenant->id)->first();

        if (!$tenantUser) {
            return 'staff';
        }

        $roleId = $tenantUser->pivot->role_id;

        // Map tenant roles to school roles
        $roleMapping = [
            1 => 'principal', // super_admin -> principal
            2 => 'admin',     // owner -> admin
            3 => 'admin',     // admin -> admin
            4 => 'student',   // student -> student
            5 => 'teacher',   // teacher -> teacher
            6 => 'parent',    // parent -> parent
        ];

        return $roleMapping[$roleId] ?? 'staff';
    }
}
