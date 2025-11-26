<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Subject;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\User;
use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\DB;

class GradeEntriesTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Buscar ou criar tenant e school
            $tenant = Tenant::first();
            if (!$tenant) {
                $tenant = Tenant::create([
                    'name' => 'Test Tenant',
                    'slug' => 'test-tenant',
                    'domain' => 'test.local',
                    'database' => 'test_db',
                    'is_active' => true,
                    'owner_id' => 1,
                    'created_by' => 1,
                ]);
            }

            $school = School::where('tenant_id', $tenant->id)->first();
            if (!$school) {
                $school = School::create([
                    'tenant_id' => $tenant->id,
                    'school_code' => 'SCH001',
                    'official_name' => 'Escola Teste',
                    'display_name' => 'Escola Teste',
                    'status' => 'active',
                ]);
            }

            // Buscar ou criar academic year e term
            $academicYear = AcademicYear::where('school_id', $school->id)->first();
            if (!$academicYear) {
                $academicYear = AcademicYear::create([
                    'tenant_id' => $tenant->id,
                    'school_id' => $school->id,
                    'name' => '2024/2025',
                    'start_date' => now()->startOfYear(),
                    'end_date' => now()->endOfYear(),
                    'is_current' => true,
                    'status' => 'active',
                ]);
            }

            $academicTerm = AcademicTerm::where('school_id', $school->id)->first();
            if (!$academicTerm) {
                $academicTerm = AcademicTerm::create([
                    'tenant_id' => $tenant->id,
                    'school_id' => $school->id,
                    'academic_year_id' => $academicYear->id,
                    'name' => '1º Semestre',
                    'code' => 'S1',
                    'start_date' => now()->startOfYear(),
                    'end_date' => now()->startOfYear()->addMonths(6),
                    'is_current' => true,
                    'status' => 'active',
                ]);
            }

            // Buscar ou criar subject
            $subject = Subject::where('school_id', $school->id)->first();
            if (!$subject) {
                $subject = Subject::create([
                    'tenant_id' => $tenant->id,
                    'school_id' => $school->id,
                    'name' => 'Matemática',
                    'code' => 'MAT',
                    'description' => 'Matemática Geral',
                    'grade_levels' => json_encode(['1st Grade', '2nd Grade', '3rd Grade']),
                    'status' => 'active',
                ]);
            }

            // Criar 4 estudantes com usuários
            $students = [];
            $names = [
                ['first_name' => 'Ana', 'last_name' => 'Silva'],
                ['first_name' => 'Bruno', 'last_name' => 'Santos'],
                ['first_name' => 'Carlos', 'last_name' => 'Oliveira'],
                ['first_name' => 'Diana', 'last_name' => 'Costa'],
            ];

            foreach ($names as $index => $name) {
                $studentNumber = 'STU' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                $email = strtolower($name['first_name'] . '.' . $name['last_name'] . '@test.com');
                
                // Criar usuário para o estudante
                $user = User::firstOrCreate(
                    ['identifier' => $email],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $name['first_name'] . ' ' . $name['last_name'],
                        'identifier' => $email,
                        'type' => 'email',
                        'password' => bcrypt('password'),
                        'is_active' => true,
                    ]
                );
                
                $students[] = Student::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'school_id' => $school->id,
                    'student_number' => $studentNumber,
                    'first_name' => $name['first_name'],
                    'last_name' => $name['last_name'],
                    'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
                    'gender' => ['female', 'male', 'male', 'female'][$index],
                    'admission_date' => now()->subMonths(6)->format('Y-m-d'),
                    'current_grade_level' => '5th Grade',
                    'current_academic_year_id' => $academicYear->id,
                    'enrollment_status' => 'enrolled',
                    'behavioral_points' => 0,
                ]);
            }

            // Criar 2 turmas
            $class1 = AcademicClass::create([
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $academicYear->id,
                'academic_term_id' => $academicTerm->id,
                'name' => 'Matemática - Turma A',
                'class_code' => 'MAT-A-2024',
                'grade_level' => '5th Grade',
                'max_students' => 30,
                'current_enrollment' => 3,
                'status' => 'active',
            ]);

            $class2 = AcademicClass::create([
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $academicYear->id,
                'academic_term_id' => $academicTerm->id,
                'name' => 'Matemática - Turma B',
                'class_code' => 'MAT-B-2024',
                'grade_level' => '5th Grade',
                'max_students' => 30,
                'current_enrollment' => 1,
                'status' => 'active',
            ]);

            // Matricular 3 estudantes na turma 1
            foreach (array_slice($students, 0, 3) as $student) {
                $class1->students()->attach($student->id, [
                    'enrollment_date' => now(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Matricular 1 estudante na turma 2 (o último)
            $class2->students()->attach($students[3]->id, [
                'enrollment_date' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->command->info('✅ Dados de teste criados com sucesso!');
            $this->command->info("📚 Turma 1 ({$class1->name}): 3 estudantes");
            $this->command->info("📚 Turma 2 ({$class2->name}): 1 estudante");
            $this->command->info("👥 Estudantes criados: " . count($students));

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Erro: ' . $e->getMessage());
            $this->command->error($e->getTraceAsString());
            throw $e;
        }
    }
}

