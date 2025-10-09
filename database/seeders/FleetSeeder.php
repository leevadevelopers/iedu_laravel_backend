<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1\Transport\FleetBus;
use App\Models\V1\SIS\School\School;

class FleetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first school
        $school = School::first();
        
        if (!$school) {
            $this->command->error('No school found. Please run schools seeder first.');
            return;
        }

        $fleetBuses = [
            [
                'school_id' => $school->id,
                'license_plate' => 'ABC-1234',
                'internal_code' => 'BUS-001',
                'make' => 'Mercedes-Benz',
                'model' => 'Sprinter 515',
                'manufacture_year' => 2022,
                'capacity' => 25,
                'current_capacity' => 20,
                'fuel_type' => 'diesel',
                'fuel_consumption_per_km' => 12.5,
                'gps_device_id' => 'GPS-FLEET-001',
                'safety_features' => ['seat_belts', 'gps_tracking', 'camera_system'],
                'last_inspection_date' => '2024-08-15',
                'next_inspection_due' => '2025-08-15',
                'insurance_expiry' => '2025-06-30',
                'status' => 'active',
                'notes' => 'Veículo em excelente estado, utilizado apenas para transporte escolar'
            ],
            [
                'school_id' => $school->id,
                'license_plate' => 'DEF-5678',
                'internal_code' => 'BUS-002',
                'make' => 'Volkswagen',
                'model' => 'Crafter',
                'manufacture_year' => 2021,
                'capacity' => 20,
                'current_capacity' => 18,
                'fuel_type' => 'diesel',
                'fuel_consumption_per_km' => 11.8,
                'gps_device_id' => 'GPS-FLEET-002',
                'safety_features' => ['seat_belts', 'gps_tracking'],
                'last_inspection_date' => '2024-07-20',
                'next_inspection_due' => '2025-07-20',
                'insurance_expiry' => '2025-05-15',
                'status' => 'active',
                'notes' => 'Veículo higienizado e desinfetado regularmente'
            ],
            [
                'school_id' => $school->id,
                'license_plate' => 'GHI-9012',
                'internal_code' => 'BUS-003',
                'make' => 'Iveco',
                'model' => 'Daily Van',
                'manufacture_year' => 2020,
                'capacity' => 15,
                'current_capacity' => 12,
                'fuel_type' => 'diesel',
                'fuel_consumption_per_km' => 10.2,
                'gps_device_id' => 'GPS-FLEET-003',
                'safety_features' => ['seat_belts', 'gps_tracking', 'first_aid_kit'],
                'last_inspection_date' => '2024-09-10',
                'next_inspection_due' => '2025-09-10',
                'insurance_expiry' => '2025-04-28',
                'status' => 'maintenance',
                'notes' => 'Em manutenção programada - troca de óleo e filtros'
            ],
            [
                'school_id' => $school->id,
                'license_plate' => 'JKL-3456',
                'internal_code' => 'BUS-004',
                'make' => 'Ford',
                'model' => 'Transit',
                'manufacture_year' => 2024,
                'capacity' => 18,
                'current_capacity' => 0,
                'fuel_type' => 'electric',
                'fuel_consumption_per_km' => null,
                'gps_device_id' => 'GPS-FLEET-004',
                'safety_features' => ['seat_belts', 'gps_tracking', 'emergency_exit'],
                'last_inspection_date' => '2024-08-05',
                'next_inspection_due' => '2025-08-05',
                'insurance_expiry' => '2025-03-12',
                'status' => 'out_of_service',
                'notes' => 'Veículo apresentando problema elétrico - aguardando reparo'
            ],
            [
                'school_id' => $school->id,
                'license_plate' => 'MNO-7890',
                'internal_code' => 'BUS-005',
                'make' => 'Renault',
                'model' => 'Master',
                'manufacture_year' => 2019,
                'capacity' => 22,
                'current_capacity' => 22,
                'fuel_type' => 'hybrid',
                'fuel_consumption_per_km' => 8.5,
                'gps_device_id' => 'GPS-FLEET-005',
                'safety_features' => ['seat_belts', 'gps_tracking', 'climate_control'],
                'last_inspection_date' => '2024-10-01',
                'next_inspection_due' => '2025-10-01',
                'insurance_expiry' => '2025-02-20',
                'status' => 'active',
                'notes' => 'Veículo híbrido - baixo consumo e emissões'
            ]
        ];

        foreach ($fleetBuses as $fleetBusData) {
            FleetBus::create($fleetBusData);
        }

        $this->command->info('Fleet seeder completed successfully. Created ' . count($fleetBuses) . ' vehicles.');
    }
}
