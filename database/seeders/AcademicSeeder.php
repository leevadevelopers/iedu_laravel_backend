<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\Academic\GradingSystem;
use App\Models\V1\Academic\GradeScale;
use App\Models\V1\Academic\GradeLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $tenantId = $this->getOrCreateTenant();
            $schoolId = $this->getOrCreateSchool($tenantId);
            $userId = $this->getOrCreateUser($tenantId);

            $subjects = $this->createSubjects($tenantId, $schoolId);
            $teachers = $this->createTeachers($tenantId, $schoolId, $userId);
            $gradingSystem = $this->createGradingSystem($tenantId, $schoolId);
            $gradeScales = $this->createGradeScales($tenantId, $schoolId, $gradingSystem->id);

            $this->createGradeLevels($tenantId, $schoolId, $gradeScales);

            DB::commit();

            $this->command->info('✅ Academic data seeded successfully!');
            $this->command->info("📊 Created: " . count($subjects) . " subjects, " . count($teachers) . " teachers");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getOrCreateTenant(): int
    {
        $tenant = DB::table('tenants')->first();
        return $tenant ? $tenant->id : DB::table('tenants')->insertGetId([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corporation',
            'owner_id' => 1,
            'is_active' => true,
            'settings' => json_encode(['timezone' => 'UTC']),
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getOrCreateSchool(int $tenantId): int
    {
        $school = DB::table('schools')->where('tenant_id', $tenantId)->first();
        return $school ? $school->id : DB::table('schools')->insertGetId([
            'tenant_id' => $tenantId,
            'school_code' => 'ACME001',
            'official_name' => 'Acme Academy',
            'display_name' => 'Acme Academy',
            'short_name' => 'Acme',
            'school_type' => 'private',
            'educational_levels' => json_encode(['elementary', 'middle', 'high']),
            'grade_range_min' => '1',
            'grade_range_max' => '12',
            'email' => 'info@acmeacademy.edu',
            'country_code' => 'US',
            'city' => 'Learning City',
            'timezone' => 'America/New_York',
            'accreditation_status' => 'accredited',
            'academic_calendar_type' => 'semester',
            'academic_year_start_month' => 8,
            'grading_system' => 'traditional_letter',
            'student_capacity' => 1000,
            'current_enrollment' => 500,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getOrCreateUser(int $tenantId): int
    {
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => 'Admin User',
                'identifier' => 'admin@acmeacademy.edu',
                'type' => 'email',
                'password' => bcrypt('password'),
            ]);
        }
        return $user->id;
    }

    private function createSubjects(int $tenantId, int $schoolId): array
    {
        $subjects = [
            [
                'name' => 'Mathematics',
                'code' => 'MATH101',
                'description' => 'Basic mathematics covering algebra and geometry',
                'subject_area' => 'mathematics',
                'grade_levels' => ['7', '8', '9', '10', '11', '12'],
                'credit_hours' => 1.0,
                'is_core_subject' => true,
                'is_elective' => false,
                'status' => 'active',
            ],
            [
                'name' => 'English Language Arts',
                'code' => 'ELA101',
                'description' => 'Reading, writing, and communication skills',
                'subject_area' => 'language_arts',
                'grade_levels' => ['7', '8', '9', '10', '11', '12'],
                'credit_hours' => 1.0,
                'is_core_subject' => true,
                'is_elective' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Science',
                'code' => 'SCI101',
                'description' => 'General science covering biology, chemistry, and physics',
                'subject_area' => 'science',
                'grade_levels' => ['7', '8', '9', '10', '11', '12'],
                'credit_hours' => 1.0,
                'is_core_subject' => true,
                'is_elective' => false,
                'status' => 'active',
            ],
        ];

        $createdSubjects = [];
        foreach ($subjects as $subjectData) {
            $subjectData['tenant_id'] = $tenantId;
            $subjectData['school_id'] = $schoolId;

            // Check if subject already exists
            $existingSubject = Subject::where('school_id', $schoolId)
                ->where('code', $subjectData['code'])
                ->first();

            if (!$existingSubject) {
                $createdSubjects[] = Subject::create($subjectData);
            } else {
                $createdSubjects[] = $existingSubject;
            }
        }

        return $createdSubjects;
    }

    private function createTeachers(int $tenantId, int $schoolId, int $userId): array
    {
        $teachers = [
            [
                'employee_id' => 'TCH001',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'title' => 'Mr.',
                'employment_type' => 'full_time',
                'hire_date' => '2020-08-15',
                'status' => 'active',
                'department' => 'Mathematics',
                'position' => 'Senior Teacher',
                'specializations_json' => ['mathematics', 'algebra', 'geometry'],
            ],
            [
                'employee_id' => 'TCH002',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'title' => 'Ms.',
                'employment_type' => 'full_time',
                'hire_date' => '2019-08-15',
                'status' => 'active',
                'department' => 'English',
                'position' => 'Lead Teacher',
                'specializations_json' => ['language_arts', 'literature', 'writing'],
            ],
        ];

        $createdTeachers = [];
        foreach ($teachers as $teacherData) {
            $teacherData['tenant_id'] = $tenantId;
            $teacherData['school_id'] = $schoolId;
            $teacherData['user_id'] = $userId;

            // Check if teacher already exists
            $existingTeacher = Teacher::where('school_id', $schoolId)
                ->where('employee_id', $teacherData['employee_id'])
                ->first();

            if (!$existingTeacher) {
                $createdTeachers[] = Teacher::create($teacherData);
            } else {
                $createdTeachers[] = $existingTeacher;
            }
        }

        return $createdTeachers;
    }

    private function createGradingSystem(int $tenantId, int $schoolId): GradingSystem
    {
        return GradingSystem::create([
            'tenant_id' => $tenantId,
            'school_id' => $schoolId,
            'name' => 'Traditional Letter Grades',
            'system_type' => 'traditional_letter',
            'applicable_grades' => ['7', '8', '9', '10', '11', '12'],
            'applicable_subjects' => ['mathematics', 'language_arts', 'science', 'social_studies'],
            'is_primary' => true,
            'configuration_json' => [
                'passing_grade' => 'D',
                'honor_roll_minimum' => 'B',
                'dean_list_minimum' => 'A',
            ],
            'status' => 'active',
        ]);
    }

    private function createGradeScales(int $tenantId, int $schoolId, int $gradingSystemId): array
    {
        $gradeScales = [
            [
                'name' => 'Letter Grade Scale',
                'scale_type' => 'letter',
                'is_default' => true,
            ],
        ];

        $createdScales = [];
        foreach ($gradeScales as $scaleData) {
            $scaleData['tenant_id'] = $tenantId;
            $scaleData['school_id'] = $schoolId;
            $scaleData['grading_system_id'] = $gradingSystemId;
            $createdScales[] = GradeScale::create($scaleData);
        }

        return $createdScales;
    }

    private function createGradeLevels(int $tenantId, int $schoolId, array $gradeScales): void
    {
        $letterScale = $gradeScales[0];

        $letterGrades = [
            ['grade_value' => 'A', 'display_value' => 'A', 'numeric_value' => 93.0, 'gpa_points' => 4.0, 'percentage_min' => 93.0, 'percentage_max' => 96.9, 'is_passing' => true, 'sort_order' => 1],
            ['grade_value' => 'B', 'display_value' => 'B', 'numeric_value' => 83.0, 'gpa_points' => 3.0, 'percentage_min' => 83.0, 'percentage_max' => 86.9, 'is_passing' => true, 'sort_order' => 2],
            ['grade_value' => 'C', 'display_value' => 'C', 'numeric_value' => 73.0, 'gpa_points' => 2.0, 'percentage_min' => 73.0, 'percentage_max' => 76.9, 'is_passing' => true, 'sort_order' => 3],
            ['grade_value' => 'D', 'display_value' => 'D', 'numeric_value' => 63.0, 'gpa_points' => 1.0, 'percentage_min' => 63.0, 'percentage_max' => 66.9, 'is_passing' => true, 'sort_order' => 4],
            ['grade_value' => 'F', 'display_value' => 'F', 'numeric_value' => 0.0, 'gpa_points' => 0.0, 'percentage_min' => 0.0, 'percentage_max' => 59.9, 'is_passing' => false, 'sort_order' => 5],
        ];

        foreach ($letterGrades as $gradeData) {
            $gradeData['tenant_id'] = $tenantId;
            $gradeData['grade_scale_id'] = $letterScale->id;

            // Check if grade level already exists
            $existingGrade = GradeLevel::where('tenant_id', $tenantId)
                ->where('grade_scale_id', $letterScale->id)
                ->where('grade_value', $gradeData['grade_value'])
                ->first();

            if (!$existingGrade) {
                GradeLevel::create($gradeData);
            }
        }
    }
}
