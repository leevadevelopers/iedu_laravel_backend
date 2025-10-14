<?php

namespace Database\Seeders;

use App\Models\Assessment\AssessmentType;
use Illuminate\Database\Seeder;

class AssessmentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Teste',
                'code' => 'TEST',
                'description' => 'Avaliação escrita individual',
                'default_weight' => 20.00,
                'color' => '#3B82F6',
            ],
            [
                'name' => 'Trabalho',
                'code' => 'WORK',
                'description' => 'Trabalho individual ou em grupo',
                'default_weight' => 15.00,
                'color' => '#10B981',
            ],
            [
                'name' => 'Exame',
                'code' => 'EXAM',
                'description' => 'Exame final',
                'default_weight' => 40.00,
                'color' => '#EF4444',
            ],
            [
                'name' => 'Apresentação',
                'code' => 'PRESENTATION',
                'description' => 'Apresentação oral',
                'default_weight' => 10.00,
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Projeto',
                'code' => 'PROJECT',
                'description' => 'Projeto prático',
                'default_weight' => 25.00,
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Participação',
                'code' => 'PARTICIPATION',
                'description' => 'Participação nas aulas',
                'default_weight' => 5.00,
                'color' => '#06B6D4',
            ],
        ];

        foreach ($types as $type) {
            AssessmentType::firstOrCreate(
                ['code' => $type['code'], 'tenant_id' => 1], // Assuming tenant_id 1 exists
                $type
            );
        }

        $this->command->info('Assessment types seeded successfully!');
    }
}

