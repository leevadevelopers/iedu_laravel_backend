<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\V1\SIS\School\School;
use App\Models\Settings\Tenant;
use App\Models\User;

class AcademicTermsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->createAcademicDataForTenant($tenant);
        }
    }

    private function createAcademicDataForTenant(Tenant $tenant): void
    {
        // Get the first school for this tenant
        $school = School::where('tenant_id', $tenant->id)->first();
        
        if (!$school) {
            $this->command->warn("No school found for tenant {$tenant->name}");
            return;
        }

        // Get the first user for this tenant
        $user = User::where('tenant_id', $tenant->id)->first();
        
        if (!$user) {
            $this->command->warn("No user found for tenant {$tenant->name}");
            return;
        }

        // Create academic year
        $academicYear = AcademicYear::create([
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'name' => 'Ano Letivo 2024-2025',
            'year' => '2024-2025',
            'description' => 'Ano letivo de teste',
            'start_date' => '2024-08-01',
            'end_date' => '2025-07-31',
            'enrollment_start_date' => '2024-06-01',
            'enrollment_end_date' => '2024-07-15',
            'registration_deadline' => '2024-07-31',
            'term_structure' => 'semesters',
            'total_terms' => 2,
            'total_instructional_days' => 180,
            'status' => 'active',
            'is_current' => true,
            'created_by' => $user->id,
        ]);

        // Create academic terms
        $terms = [
            [
                'name' => '1º Semestre',
                'description' => 'Primeiro semestre do ano letivo',
                'start_date' => '2024-08-01',
                'end_date' => '2024-12-20',
                'is_current' => true,
                'status' => 'active',
            ],
            [
                'name' => '2º Semestre',
                'description' => 'Segundo semestre do ano letivo',
                'start_date' => '2025-01-15',
                'end_date' => '2025-07-31',
                'is_current' => false,
                'status' => 'planned',
            ],
        ];

        foreach ($terms as $termData) {
            AcademicTerm::create([
                'tenant_id' => $tenant->id,
                'academic_year_id' => $academicYear->id,
                'name' => $termData['name'],
                'description' => $termData['description'],
                'start_date' => $termData['start_date'],
                'end_date' => $termData['end_date'],
                'is_current' => $termData['is_current'],
                'status' => $termData['status'],
                'created_by' => $user->id,
            ]);
        }

        $this->command->info("✅ Created academic data for tenant {$tenant->name}");
    }
}
