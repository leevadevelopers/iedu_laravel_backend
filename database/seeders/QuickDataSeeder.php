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
use App\Models\Settings\Tenant;

class QuickDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create tenant
        $tenant = Tenant::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'domain' => 'test.example.com',
            'database' => 'test_db',
            'settings' => ['timezone' => 'UTC', 'locale' => 'en', 'currency' => 'USD'],
            'is_active' => true,
            'created_by' => 1,
            'owner_id' => 1
        ]);

        // Create school
        $school = School::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test School',
            'code' => 'TS001',
            'address' => '123 Test Street',
            'phone' => '+1234567890',
            'email' => 'test@school.com',
            'website' => 'https://test.school.com',
            'status' => 'active'
        ]);

        // Create user
        $user = User::create([
            'name' => 'Test User',
            'identifier' => 'test@test.com',
            'type' => 'email',
            'password' => bcrypt('password'),
            'verified_at' => now(),
            'role_id' => 1,
            'is_active' => true
        ]);

        // Create student
        $student = Student::create([
            'tenant_id' => $tenant->id,
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

        // Create bus
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

        // Create route
        $route = TransportRoute::create([
            'school_id' => $school->id,
            'name' => 'Rota Centro',
            'code' => 'R001',
            'waypoints' => [
                ['lat' => -23.5505, 'lng' => -46.6333],
                ['lat' => -23.5400, 'lng' => -46.6200]
            ],
            'departure_time' => '07:00:00',
            'arrival_time' => '08:00:00',
            'estimated_duration_minutes' => 60,
            'total_distance_km' => 15.5,
            'status' => 'active',
            'shift' => 'morning',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
        ]);

        // Create bus stop
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

        // Create transport events
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
            ],
            [
                'school_id' => $school->id,
                'student_id' => $student->id,
                'fleet_bus_id' => $bus->id,
                'bus_stop_id' => $stop->id,
                'transport_route_id' => $route->id,
                'event_type' => 'check_in',
                'event_timestamp' => now()->subDays(1)->subHours(2),
                'validation_method' => 'rfid',
                'validation_data' => 'RFID789',
                'recorded_by' => $user->id,
                'is_automated' => true,
                'notes' => 'Check-in RFID'
            ]
        ];

        foreach ($events as $eventData) {
            StudentTransportEvent::create($eventData);
        }

        $this->command->info('Created test data: Tenant, School, User, Student, Bus, Route, Stop, and 3 Transport Events');
    }
}
