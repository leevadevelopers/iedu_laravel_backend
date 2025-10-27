<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\SchoolUser;

class SchoolUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users and schools
        $users = User::all();
        $schools = School::all();

        if ($users->isEmpty() || $schools->isEmpty()) {
            $this->command->warn('No users or schools found. Please run UserSeeder and SchoolSeeder first.');
            return;
        }

        // Associate all users with all schools as admin
        foreach ($users as $user) {
            foreach ($schools as $school) {
                // Check if association already exists
                $existingAssociation = SchoolUser::where('user_id', $user->id)
                    ->where('school_id', $school->id)
                    ->first();

                if (!$existingAssociation) {
                    SchoolUser::create([
                        'user_id' => $user->id,
                        'school_id' => $school->id,
                        'role' => 'admin',
                        'status' => 'active',
                        'start_date' => now()->subDays(30),
                        'permissions' => json_encode([
                            'can_manage_users' => true,
                            'can_manage_grades' => true,
                            'can_manage_classes' => true,
                            'can_view_reports' => true,
                        ])
                    ]);

                    $this->command->info("Associated user {$user->name} with school {$school->name}");
                }
            }
        }

        $this->command->info('School user associations created successfully!');
    }
}