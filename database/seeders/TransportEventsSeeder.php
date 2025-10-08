<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\BusStop;
use App\Models\V1\Transport\TransportRoute;
use App\Models\User;
use Carbon\Carbon;

class TransportEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $school = School::first();
        $students = Student::take(10)->get();
        $buses = FleetBus::take(3)->get();
        $stops = BusStop::take(5)->get();
        $routes = TransportRoute::take(3)->get();
        $users = User::take(3)->get();

        if (!$school || $students->isEmpty() || $buses->isEmpty() || $stops->isEmpty() || $routes->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Required data not found. Please run other seeders first.');
            return;
        }

        $events = [];

        // Generate events for the last 30 days
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::now()->subDays($i);
            
            // Generate 5-15 events per day
            $eventsPerDay = rand(5, 15);
            
            for ($j = 0; $j < $eventsPerDay; $j++) {
                $student = $students->random();
                $bus = $buses->random();
                $stop = $stops->random();
                $route = $routes->random();
                $user = $users->random();
                
                // Random time between 6:00 and 18:00
                $hour = rand(6, 18);
                $minute = rand(0, 59);
                $eventTime = $date->copy()->setTime($hour, $minute);
                
                $eventTypes = ['check_in', 'check_out', 'no_show', 'early_exit'];
                $validationMethods = ['qr_code', 'rfid', 'manual', 'facial_recognition'];
                
                $eventType = $eventTypes[array_rand($eventTypes)];
                $validationMethod = $validationMethods[array_rand($validationMethods)];
                
                $events[] = [
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'fleet_bus_id' => $bus->id,
                    'bus_stop_id' => $stop->id,
                    'transport_route_id' => $route->id,
                    'event_type' => $eventType,
                    'event_timestamp' => $eventTime,
                    'validation_method' => $validationMethod,
                    'validation_data' => $validationMethod === 'qr_code' ? 'QR' . rand(100000, 999999) : 
                                       ($validationMethod === 'rfid' ? 'RFID' . rand(1000, 9999) : null),
                    'recorded_by' => $user->id,
                    'event_latitude' => -23.5505 + (rand(-100, 100) / 10000), // São Paulo area
                    'event_longitude' => -46.6333 + (rand(-100, 100) / 10000),
                    'is_automated' => rand(0, 1) === 1,
                    'notes' => $this->getRandomNote($eventType),
                    'metadata' => [
                        'device_id' => 'DEV' . rand(1000, 9999),
                        'app_version' => '1.0.' . rand(1, 10),
                        'location_accuracy' => rand(1, 10) . 'm'
                    ],
                    'created_at' => $eventTime,
                    'updated_at' => $eventTime,
                ];
            }
        }

        // Insert events in batches
        $chunks = array_chunk($events, 100);
        foreach ($chunks as $chunk) {
            StudentTransportEvent::insert($chunk);
        }

        $this->command->info('Created ' . count($events) . ' transport events');
    }

    private function getRandomNote(string $eventType): string
    {
        $notes = [
            'check_in' => [
                'Estudante embarcou com sucesso',
                'Check-in realizado via QR Code',
                'Validação automática funcionando',
                'Estudante presente no horário',
                'Embarque registrado pelo motorista'
            ],
            'check_out' => [
                'Estudante desembarcou com segurança',
                'Check-out realizado via RFID',
                'Desembarque no ponto correto',
                'Estudante chegou ao destino',
                'Saída registrada pelo assistente'
            ],
            'no_show' => [
                'Estudante não compareceu',
                'Falta não justificada',
                'Estudante ausente no embarque',
                'Não localizado no ponto',
                'Falta registrada pelo sistema'
            ],
            'early_exit' => [
                'Estudante saiu antes do destino',
                'Desembarque antecipado',
                'Saída não programada',
                'Estudante solicitou parada extra',
                'Emergência familiar'
            ]
        ];

        $eventNotes = $notes[$eventType] ?? ['Evento registrado'];
        return $eventNotes[array_rand($eventNotes)];
    }
}
