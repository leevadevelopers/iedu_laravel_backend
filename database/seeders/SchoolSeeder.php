<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Settings\Tenant;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get tenant 1
        $tenant = Tenant::find(1);
        if (!$tenant) {
            $this->command->warn('Tenant ID 1 not found. Please run TenantSeeder first.');
            return;
        }

        // Check if school already exists for this tenant
        $existingSchool = School::where('tenant_id', $tenant->id)->first();
        
        if ($existingSchool) {
            $this->command->info("School already exists for Tenant {$tenant->id}: {$existingSchool->display_name}");
            $school = $existingSchool;
        } else {
            // Create a default school for tenant 1
            $school = School::create([
                'tenant_id' => $tenant->id,
                'school_code' => 'SCH001',
                'official_name' => 'Escola Exemplo',
                'display_name' => 'Escola Exemplo',
                'short_name' => 'Exemplo',
                'school_type' => 'primary',
                'educational_levels' => ['elementary', 'middle'],
                'grade_range_min' => '1',
                'grade_range_max' => '8',
                'email' => 'escola@example.com',
                'phone' => '+258841234567',
                'website' => null,
                'address_json' => [
                    'street' => 'Rua Principal',
                    'city' => 'Maputo',
                    'state' => 'Maputo',
                    'postal_code' => '1100',
                    'country' => 'MZ'
                ],
                'country_code' => 'MZ',
                'state_province' => 'Maputo',
                'city' => 'Maputo',
                'timezone' => 'Africa/Maputo',
                'ministry_education_code' => null,
                'accreditation_status' => 'accredited',
                'academic_calendar_type' => 'trimester',
                'academic_year_start_month' => 8,
                'grading_system' => 'traditional_letter',
                'attendance_tracking_level' => 'daily',
                'educational_philosophy' => null,
                'language_instruction' => ['pt', 'en'],
                'religious_affiliation' => null,
                'student_capacity' => 500,
                'current_enrollment' => 0,
                'staff_count' => 0,
                'subscription_plan' => 'standard',
                'feature_flags' => [
                    'attendance' => true,
                    'grades' => true,
                    'financial' => true,
                    'library' => true,
                    'transport' => true,
                ],
                'integration_settings' => [],
                'branding_configuration' => [
                    'primary_color' => '#3B82F6',
                    'secondary_color' => '#10B981',
                    'logo' => null,
                ],
                'status' => 'active',
                'established_date' => now()->subYears(5),
                'onboarding_completed_at' => now(),
                'trial_ends_at' => now()->addMonths(3),
            ]);

            $this->command->info("Created School: {$school->display_name} (ID: {$school->id}) for Tenant: {$tenant->name}");
        }

        // Associate users with the school
        $this->associateUsersWithSchool($school, $tenant);
    }

    /**
     * Associate users with the school based on their roles
     */
    private function associateUsersWithSchool(School $school, Tenant $tenant): void
    {
        // Get all users that belong to this tenant
        // Use the pivot table name directly in whereHas
        $users = User::whereHas('tenants', function ($query) use ($tenant) {
            $query->where('tenants.id', $tenant->id)
                  ->where('tenant_users.status', 'active');
        })->get();

        $associatedCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            // Skip super admin
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                continue;
            }

            // Check if user is already associated with this school
            if ($user->schools()->where('schools.id', $school->id)->exists()) {
                $skippedCount++;
                continue;
            }

            // Determine role based on user's tenant role
            $userRole = $this->determineSchoolRole($user);

            // Associate user with school
            $user->schools()->attach($school->id, [
                'role' => $userRole,
                'status' => 'active',
                'start_date' => now(),
                'end_date' => null,
                'permissions' => null,
            ]);

            $associatedCount++;
            $this->command->info("  ✓ Associated {$user->name} ({$user->identifier}) with school as '{$userRole}'");
        }

        $this->command->info("\nSummary:");
        $this->command->info("  - New associations: {$associatedCount}");
        $this->command->info("  - Already associated: {$skippedCount}");
        $this->command->info("  - Total users in tenant: " . $users->count());
    }

    /**
     * Determine school role based on user's tenant role
     */
    private function determineSchoolRole(User $user): string
    {
        // Get user's role from tenant_users pivot
        $tenantUser = DB::table('tenant_users')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($tenantUser && $tenantUser->role_id) {
            $role = DB::table('roles')->where('id', $tenantUser->role_id)->first();
            
            if ($role) {
                // Map tenant roles to school roles
                // Valid enum values: 'owner','admin','teacher','staff','principal','counselor','nurse','librarian','coach','volunteer','student'
                $roleMapping = [
                    'school_owner' => 'owner',
                    'school_admin' => 'admin',
                    'director' => 'admin',
                    'principal' => 'principal',
                    'teacher' => 'teacher',
                    'secretary' => 'staff',
                    'accountant' => 'staff',
                    'counselor' => 'counselor',
                    'nurse' => 'nurse',
                    'librarian' => 'librarian',
                    'coach' => 'coach',
                    'parent' => 'staff',  // 'parent' not in enum, use 'staff'
                    'student' => 'student',
                ];

                return $roleMapping[$role->name] ?? 'staff';
            }
        }

        // Fallback: Check if user has role via Spatie
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('school_owner')) {
                return 'owner';
            } elseif ($user->hasRole('school_admin')) {
                return 'admin';
            } elseif ($user->hasRole('teacher')) {
                return 'teacher';
            } elseif ($user->hasRole('principal')) {
                return 'principal';
            }
        }

        return 'staff';
    }
}

