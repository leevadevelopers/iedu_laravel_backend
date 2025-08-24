<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Forms\FormTemplate;
use App\Models\Settings\Tenant;

class SchoolFormTemplatesSeeder extends Seeder
{
    public function run()
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->createSchoolFormTemplates($tenant);
        }
    }

    private function createSchoolFormTemplates(Tenant $tenant)
    {
        // Student Enrollment Form
        $this->createStudentEnrollmentForm($tenant);

        // Student Registration Form
        $this->createStudentRegistrationForm($tenant);

        // Attendance Form
        $this->createAttendanceForm($tenant);
    }

    private function createStudentEnrollmentForm(Tenant $tenant)
    {
        FormTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Student Enrollment Form',
            'description' => 'Complete student enrollment form for new students',
            'category' => 'student_enrollment',
            'version' => '1.0',
            'is_multi_step' => true,
            'auto_save' => true,
            'compliance_level' => 'strict',
            'is_active' => true,
            'is_default' => true,
            'created_by' => 1,
            'steps' => [
                [
                    'step_number' => 1,
                    'title' => 'Student Information',
                    'sections' => [
                        [
                            'title' => 'Personal Details',
                            'fields' => [
                                [
                                    'field_id' => 'first_name',
                                    'label' => 'First Name',
                                    'type' => 'text',
                                    'required' => true,
                                    'validation' => 'required|string|max:255'
                                ],
                                [
                                    'field_id' => 'last_name',
                                    'label' => 'Last Name',
                                    'type' => 'text',
                                    'required' => true,
                                    'validation' => 'required|string|max:255'
                                ],
                                [
                                    'field_id' => 'date_of_birth',
                                    'label' => 'Date of Birth',
                                    'type' => 'date',
                                    'required' => true,
                                    'validation' => 'required|date|before:today'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'form_configuration' => [
                'theme' => 'school_theme',
                'show_progress_bar' => true,
                'allow_step_navigation' => true,
                'auto_save_interval' => 30
            ]
        ]);
    }

    private function createStudentRegistrationForm(Tenant $tenant)
    {
        FormTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Student Registration Form',
            'description' => 'Annual student registration and information update form',
            'category' => 'student_registration',
            'version' => '1.0',
            'is_multi_step' => false,
            'auto_save' => true,
            'compliance_level' => 'standard',
            'is_active' => true,
            'is_default' => true,
            'created_by' => 1,
            'steps' => [
                [
                    'step_number' => 1,
                    'title' => 'Registration Information',
                    'sections' => [
                        [
                            'title' => 'Student Details',
                            'fields' => [
                                [
                                    'field_id' => 'student_id',
                                    'label' => 'Student ID',
                                    'type' => 'text',
                                    'required' => true,
                                    'readonly' => true
                                ],
                                [
                                    'field_id' => 'academic_year',
                                    'label' => 'Academic Year',
                                    'type' => 'select',
                                    'required' => true,
                                    'options' => ['2024-2025', '2025-2026', '2026-2027'],
                                    'default' => '2025-2026'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'form_configuration' => [
                'theme' => 'school_theme',
                'show_progress_bar' => false,
                'allow_step_navigation' => false,
                'auto_save_interval' => 60
            ]
        ]);
    }

    private function createAttendanceForm(Tenant $tenant)
    {
        FormTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Daily Attendance Form',
            'description' => 'Daily student attendance tracking form',
            'category' => 'attendance',
            'version' => '1.0',
            'is_multi_step' => false,
            'auto_save' => true,
            'compliance_level' => 'strict',
            'is_active' => true,
            'is_default' => true,
            'created_by' => 1,
            'steps' => [
                [
                    'step_number' => 1,
                    'title' => 'Attendance Record',
                    'sections' => [
                        [
                            'title' => 'Class Information',
                            'fields' => [
                                [
                                    'field_id' => 'class_id',
                                    'label' => 'Class',
                                    'type' => 'select',
                                    'required' => true,
                                    'source' => 'school_classes',
                                    'validation' => 'required|exists:school_classes,id'
                                ],
                                [
                                    'field_id' => 'date',
                                    'label' => 'Date',
                                    'type' => 'date',
                                    'required' => true,
                                    'default' => 'today',
                                    'validation' => 'required|date'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'form_configuration' => [
                'theme' => 'school_theme',
                'show_progress_bar' => false,
                'allow_step_navigation' => false,
                'auto_save_interval' => 30
            ]
        ]);
    }
}
