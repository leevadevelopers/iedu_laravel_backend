<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\BusStop;
use App\Models\V1\Transport\TransportRoute;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Models\User;

class TransportSimpleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::first();
        $user = User::first();

        if (!$school || !$user) {
            $this->command->warn('School or User not found.');
            return;
        }

        // Get or create student
        $student = Student::first();
        if (!$student) {
            $student = Student::create([
                'tenant_id' => $school->tenant_id,
                'user_id' => $user->id,
                'school_id' => $school->id,
                'student_number' => 'STU002',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'date_of_birth' => '2011-05-15',
                'gender' => 'female',
                'admission_date' => now()->toDateString(),
                'current_grade_level' => '4th Grade',
                'enrollment_status' => 'enrolled'
            ]);
        }

        // Get or create bus
        $bus = FleetBus::first();
        if (!$bus) {
            $bus = FleetBus::create([
                'school_id' => $school->id,
                'license_plate' => 'XYZ-5678',
                'internal_code' => 'BUS002',
                'make' => 'Volvo',
                'model' => 'B12',
                'manufacture_year' => 2021,
                'capacity' => 40,
                'status' => 'active'
            ]);
        }

        // Get or create route
        $route = TransportRoute::first();
        if (!$route) {
            $route = TransportRoute::create([
                'school_id' => $school->id,
                'name' => 'Rota Norte',
                'code' => 'R002',
                'waypoints' => [
                    ['lat' => -23.5505, 'lng' => -46.6333],
                    ['lat' => -23.5400, 'lng' => -46.6200]
                ],
                'departure_time' => '07:30:00',
                'arrival_time' => '08:30:00',
                'estimated_duration_minutes' => 60,
                'total_distance_km' => 20.0,
                'status' => 'active',
                'shift' => 'morning',
                'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
            ]);
        }

        // Get or create bus stop
        $stop = BusStop::first();
        if (!$stop) {
            $stop = BusStop::create([
                'school_id' => $school->id,
                'transport_route_id' => $route->id,
                'name' => 'Parada Norte',
                'code' => 'P002',
                'address' => 'Av. Norte, 456',
                'latitude' => -23.5400,
                'longitude' => -46.6200,
                'stop_order' => 1,
                'scheduled_arrival_time' => '08:00:00',
                'scheduled_departure_time' => '08:02:00',
                'status' => 'active'
            ]);
        }

        // Create some transport events
        $events = [
            [
                'school_id' => $school->id,
                'student_id' => $student->id,
                'fleet_bus_id' => $bus->id,
                'bus_stop_id' => $stop->id,
                'transport_route_id' => $route->id,
                'event_type' => 'check_in',
                'event_timestamp' => now()->subHours(2),
                'validation_method' => 'qr_code',
                'validation_data' => 'QR123456',
                'recorded_by' => $user->id,
                'is_automated' => true,
                'notes' => 'Check-in realizado com sucesso'
            ],
            [
                'school_id' => $school->id,
                'student_id' => $student->id,
                'fleet_bus_id' => $bus->id,
                'bus_stop_id' => $stop->id,
                'transport_route_id' => $route->id,
                'event_type' => 'check_out',
                'event_timestamp' => now()->subHours(1),
                'validation_method' => 'manual',
                'recorded_by' => $user->id,
                'is_automated' => false,
                'notes' => 'Check-out manual'
            ]
        ];

        foreach ($events as $eventData) {
            StudentTransportEvent::create($eventData);
        }

        $this->command->info('Created transport events data successfully');
    }
}
