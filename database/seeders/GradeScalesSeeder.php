<?php

namespace Database\Seeders;

use App\Models\V1\Academic\GradeScale;
use App\Models\V1\Academic\GradeScaleRange;
use App\Models\V1\Academic\GradingSystem;
use Illuminate\Database\Seeder;

class GradeScalesSeeder extends Seeder
{
    public function run(): void
    {
        // Verificar se já existe um sistema de notas
        $gradingSystem = GradingSystem::first();

        if (!$gradingSystem) {
            // Criar um sistema de notas padrão
            $gradingSystem = GradingSystem::create([
                'school_id' => 1,
                'tenant_id' => 1,
                'name' => 'Sistema Padrão de Avaliação',
                'system_type' => 'percentage',
                'is_primary' => true,
                'status' => 'active',
            ]);
        }

        // 1. Escala 0-20 (Sistema Português)
        $this->createPortugueseScale($gradingSystem);

        // 2. Escala A-F (Sistema Americano)
        $this->createAmericanScale($gradingSystem);

        // 3. Escala 0-100% (Sistema Percentual)
        $this->createPercentageScale($gradingSystem);

        // 4. Escala 0-10 (Sistema Brasileiro)
        $this->createBrazilianScale($gradingSystem);

        $this->command->info('Grade scales seeded successfully!');
    }

    /**
     * Escala 0-20 (Sistema Português)
     */
    protected function createPortugueseScale(GradingSystem $gradingSystem): void
    {
        $scale = GradeScale::create([
            'grading_system_id' => $gradingSystem->id,
            'school_id' => $gradingSystem->school_id,
            'tenant_id' => $gradingSystem->tenant_id,
            'name' => 'Escala 0-20',
            'scale_type' => 'points',
            'is_default' => true,
        ]);

        $ranges = [
            ['min' => 18, 'max' => 20, 'label' => '18-20', 'desc' => 'Excelente', 'color' => '#10B981', 'gpa' => 4.0, 'passing' => true],
            ['min' => 16, 'max' => 17.99, 'label' => '16-17', 'desc' => 'Muito Bom', 'color' => '#3B82F6', 'gpa' => 3.7, 'passing' => true],
            ['min' => 14, 'max' => 15.99, 'label' => '14-15', 'desc' => 'Bom', 'color' => '#06B6D4', 'gpa' => 3.3, 'passing' => true],
            ['min' => 12, 'max' => 13.99, 'label' => '12-13', 'desc' => 'Suficiente', 'color' => '#F59E0B', 'gpa' => 3.0, 'passing' => true],
            ['min' => 10, 'max' => 11.99, 'label' => '10-11', 'desc' => 'Satisfaz', 'color' => '#FBBF24', 'gpa' => 2.0, 'passing' => true],
            ['min' => 0, 'max' => 9.99, 'label' => '0-9', 'desc' => 'Insuficiente', 'color' => '#EF4444', 'gpa' => 0.0, 'passing' => false],
        ];

        foreach ($ranges as $index => $range) {
            GradeScaleRange::create([
                'grade_scale_id' => $scale->id,
                'min_value' => $range['min'],
                'max_value' => $range['max'],
                'display_label' => $range['label'],
                'description' => $range['desc'],
                'color' => $range['color'],
                'gpa_equivalent' => $range['gpa'],
                'is_passing' => $range['passing'],
                'order' => $index,
            ]);
        }
    }

    /**
     * Escala A-F (Sistema Americano)
     */
    protected function createAmericanScale(GradingSystem $gradingSystem): void
    {
        $scale = GradeScale::create([
            'grading_system_id' => $gradingSystem->id,
            'school_id' => $gradingSystem->school_id,
            'tenant_id' => $gradingSystem->tenant_id,
            'name' => 'Escala A-F',
            'scale_type' => 'letter',
            'is_default' => false,
        ]);

        $ranges = [
            ['min' => 93, 'max' => 100, 'label' => 'A', 'desc' => 'Excelente', 'color' => '#10B981', 'gpa' => 4.0, 'passing' => true],
            ['min' => 90, 'max' => 92.99, 'label' => 'A-', 'desc' => 'Muito Bom', 'color' => '#22C55E', 'gpa' => 3.7, 'passing' => true],
            ['min' => 87, 'max' => 89.99, 'label' => 'B+', 'desc' => 'Bom+', 'color' => '#3B82F6', 'gpa' => 3.3, 'passing' => true],
            ['min' => 83, 'max' => 86.99, 'label' => 'B', 'desc' => 'Bom', 'color' => '#06B6D4', 'gpa' => 3.0, 'passing' => true],
            ['min' => 80, 'max' => 82.99, 'label' => 'B-', 'desc' => 'Bom-', 'color' => '#0EA5E9', 'gpa' => 2.7, 'passing' => true],
            ['min' => 77, 'max' => 79.99, 'label' => 'C+', 'desc' => 'Satisfatório+', 'color' => '#F59E0B', 'gpa' => 2.3, 'passing' => true],
            ['min' => 73, 'max' => 76.99, 'label' => 'C', 'desc' => 'Satisfatório', 'color' => '#FBBF24', 'gpa' => 2.0, 'passing' => true],
            ['min' => 70, 'max' => 72.99, 'label' => 'C-', 'desc' => 'Satisfatório-', 'color' => '#FCD34D', 'gpa' => 1.7, 'passing' => true],
            ['min' => 67, 'max' => 69.99, 'label' => 'D+', 'desc' => 'Suficiente+', 'color' => '#FB923C', 'gpa' => 1.3, 'passing' => true],
            ['min' => 63, 'max' => 66.99, 'label' => 'D', 'desc' => 'Suficiente', 'color' => '#F97316', 'gpa' => 1.0, 'passing' => true],
            ['min' => 60, 'max' => 62.99, 'label' => 'D-', 'desc' => 'Suficiente-', 'color' => '#EA580C', 'gpa' => 0.7, 'passing' => true],
            ['min' => 0, 'max' => 59.99, 'label' => 'F', 'desc' => 'Insuficiente', 'color' => '#EF4444', 'gpa' => 0.0, 'passing' => false],
        ];

        foreach ($ranges as $index => $range) {
            GradeScaleRange::create([
                'grade_scale_id' => $scale->id,
                'min_value' => $range['min'],
                'max_value' => $range['max'],
                'display_label' => $range['label'],
                'description' => $range['desc'],
                'color' => $range['color'],
                'gpa_equivalent' => $range['gpa'],
                'is_passing' => $range['passing'],
                'order' => $index,
            ]);
        }
    }

    /**
     * Escala 0-100% (Sistema Percentual)
     */
    protected function createPercentageScale(GradingSystem $gradingSystem): void
    {
        $scale = GradeScale::create([
            'grading_system_id' => $gradingSystem->id,
            'school_id' => $gradingSystem->school_id,
            'tenant_id' => $gradingSystem->tenant_id,
            'name' => 'Escala 0-100%',
            'scale_type' => 'percentage',
            'is_default' => false,
        ]);

        $ranges = [
            ['min' => 90, 'max' => 100, 'label' => '90-100%', 'desc' => 'Excelente', 'color' => '#10B981', 'gpa' => 4.0, 'passing' => true],
            ['min' => 80, 'max' => 89.99, 'label' => '80-89%', 'desc' => 'Muito Bom', 'color' => '#3B82F6', 'gpa' => 3.5, 'passing' => true],
            ['min' => 70, 'max' => 79.99, 'label' => '70-79%', 'desc' => 'Bom', 'color' => '#06B6D4', 'gpa' => 3.0, 'passing' => true],
            ['min' => 60, 'max' => 69.99, 'label' => '60-69%', 'desc' => 'Satisfatório', 'color' => '#F59E0B', 'gpa' => 2.5, 'passing' => true],
            ['min' => 50, 'max' => 59.99, 'label' => '50-59%', 'desc' => 'Suficiente', 'color' => '#FBBF24', 'gpa' => 2.0, 'passing' => true],
            ['min' => 0, 'max' => 49.99, 'label' => '0-49%', 'desc' => 'Insuficiente', 'color' => '#EF4444', 'gpa' => 0.0, 'passing' => false],
        ];

        foreach ($ranges as $index => $range) {
            GradeScaleRange::create([
                'grade_scale_id' => $scale->id,
                'min_value' => $range['min'],
                'max_value' => $range['max'],
                'display_label' => $range['label'],
                'description' => $range['desc'],
                'color' => $range['color'],
                'gpa_equivalent' => $range['gpa'],
                'is_passing' => $range['passing'],
                'order' => $index,
            ]);
        }
    }

    /**
     * Escala 0-10 (Sistema Brasileiro)
     */
    protected function createBrazilianScale(GradingSystem $gradingSystem): void
    {
        $scale = GradeScale::create([
            'grading_system_id' => $gradingSystem->id,
            'school_id' => $gradingSystem->school_id,
            'tenant_id' => $gradingSystem->tenant_id,
            'name' => 'Escala 0-10',
            'scale_type' => 'points',
            'is_default' => false,
        ]);

        $ranges = [
            ['min' => 9, 'max' => 10, 'label' => '9-10', 'desc' => 'Excelente', 'color' => '#10B981', 'gpa' => 4.0, 'passing' => true],
            ['min' => 8, 'max' => 8.99, 'label' => '8-8.9', 'desc' => 'Ótimo', 'color' => '#22C55E', 'gpa' => 3.7, 'passing' => true],
            ['min' => 7, 'max' => 7.99, 'label' => '7-7.9', 'desc' => 'Bom', 'color' => '#3B82F6', 'gpa' => 3.3, 'passing' => true],
            ['min' => 6, 'max' => 6.99, 'label' => '6-6.9', 'desc' => 'Satisfatório', 'color' => '#F59E0B', 'gpa' => 3.0, 'passing' => true],
            ['min' => 5, 'max' => 5.99, 'label' => '5-5.9', 'desc' => 'Suficiente', 'color' => '#FBBF24', 'gpa' => 2.0, 'passing' => true],
            ['min' => 0, 'max' => 4.99, 'label' => '0-4.9', 'desc' => 'Insuficiente', 'color' => '#EF4444', 'gpa' => 0.0, 'passing' => false],
        ];

        foreach ($ranges as $index => $range) {
            GradeScaleRange::create([
                'grade_scale_id' => $scale->id,
                'min_value' => $range['min'],
                'max_value' => $range['max'],
                'display_label' => $range['label'],
                'description' => $range['desc'],
                'color' => $range['color'],
                'gpa_equivalent' => $range['gpa'],
                'is_passing' => $range['passing'],
                'order' => $index,
            ]);
        }
    }
}

