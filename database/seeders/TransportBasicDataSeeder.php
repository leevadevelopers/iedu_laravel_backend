<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\BusStop;
use App\Models\V1\Transport\TransportRoute;
use App\Models\User;

class TransportBasicDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::first();
        $user = User::first();

        if (!$school || !$user) {
            $this->command->warn('School or User not found. Please run SchoolSeeder and UserSeeder first.');
            return;
        }

        // Create a student
        $student = Student::create([
            'tenant_id' => $school->tenant_id,
            'user_id' => $user->id,
            'school_id' => $school->id,
            'student_number' => 'STU001',
            'first_name' => 'João',
            'last_name' => 'Silva',
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'admission_date' => now()->toDateString(),
            'current_grade_level' => '5th Grade',
            'enrollment_status' => 'enrolled'
        ]);

        // Create a bus
        $bus = FleetBus::create([
            'school_id' => $school->id,
            'license_plate' => 'ABC-1234',
            'internal_code' => 'BUS001',
            'make' => 'Mercedes',
            'model' => 'Sprinter',
            'manufacture_year' => 2020,
            'capacity' => 30,
            'status' => 'active'
        ]);

        // Create a route
        $route = TransportRoute::create([
            'school_id' => $school->id,
            'name' => 'Rota Centro',
            'code' => 'R001',
            'departure_time' => '07:00:00',
            'arrival_time' => '08:00:00',
            'estimated_duration_minutes' => 60,
            'total_distance_km' => 15.5,
            'status' => 'active',
            'shift' => 'morning',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
        ]);

        // Create a bus stop
        $stop = BusStop::create([
            'school_id' => $school->id,
            'transport_route_id' => $route->id,
            'name' => 'Parada Central',
            'code' => 'P001',
            'address' => 'Rua Central, 123',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'stop_order' => 1,
            'scheduled_arrival_time' => '07:30:00',
            'scheduled_departure_time' => '07:32:00',
            'status' => 'active'
        ]);

        $this->command->info('Created basic transport data: Student, Bus, Route, Stop');
    }
}
