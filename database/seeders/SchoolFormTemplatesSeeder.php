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

        // Academic Year Setup Form
        $this->createAcademicYearSetupForm($tenant);

        // Document Upload Form
        $this->createDocumentUploadForm($tenant);
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

    private function createAcademicYearSetupForm(Tenant $tenant)
    {
        FormTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Academic Year Setup Form',
            'description' => 'Form for setting up and configuring academic year details',
            'category' => 'academic_year_setup',
            'version' => '1.0',
            'is_multi_step' => true,
            'auto_save' => true,
            'compliance_level' => 'standard',
            'is_active' => true,
            'is_default' => true,
            'created_by' => 1,
            'steps' => [
                [
                    'step_number' => 1,
                    'title' => 'Basic Information',
                    'sections' => [
                        [
                            'title' => 'Academic Year Details',
                            'fields' => [
                                [
                                    'field_id' => 'name',
                                    'label' => 'Academic Year Name',
                                    'type' => 'text',
                                    'required' => true,
                                    'validation' => 'required|string|max:255'
                                ],
                                [
                                    'field_id' => 'year',
                                    'label' => 'Academic Year',
                                    'type' => 'text',
                                    'required' => true,
                                    'validation' => 'required|string|max:10'
                                ],
                                [
                                    'field_id' => 'description',
                                    'label' => 'Description',
                                    'type' => 'textarea',
                                    'required' => false,
                                    'validation' => 'nullable|string|max:1000'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'step_number' => 2,
                    'title' => 'Date Configuration',
                    'sections' => [
                        [
                            'title' => 'Academic Year Dates',
                            'fields' => [
                                [
                                    'field_id' => 'start_date',
                                    'label' => 'Start Date',
                                    'type' => 'date',
                                    'required' => true,
                                    'validation' => 'required|date'
                                ],
                                [
                                    'field_id' => 'end_date',
                                    'label' => 'End Date',
                                    'type' => 'date',
                                    'required' => true,
                                    'validation' => 'required|date|after:start_date'
                                ],
                                [
                                    'field_id' => 'enrollment_start_date',
                                    'label' => 'Enrollment Start Date',
                                    'type' => 'date',
                                    'required' => false,
                                    'validation' => 'nullable|date|after_or_equal:start_date'
                                ],
                                [
                                    'field_id' => 'enrollment_end_date',
                                    'label' => 'Enrollment End Date',
                                    'type' => 'date',
                                    'required' => false,
                                    'validation' => 'nullable|date|before:end_date'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'step_number' => 3,
                    'title' => 'Term Structure',
                    'sections' => [
                        [
                            'title' => 'Academic Term Configuration',
                            'fields' => [
                                [
                                    'field_id' => 'term_structure',
                                    'label' => 'Term Structure',
                                    'type' => 'select',
                                    'required' => false,
                                    'options' => [
                                        ['value' => 'semesters', 'label' => 'Semesters'],
                                        ['value' => 'trimesters', 'label' => 'Trimesters'],
                                        ['value' => 'quarters', 'label' => 'Quarters'],
                                        ['value' => 'year_round', 'label' => 'Year Round']
                                    ],
                                    'validation' => 'nullable|in:semesters,trimesters,quarters,year_round'
                                ],
                                [
                                    'field_id' => 'total_terms',
                                    'label' => 'Total Terms',
                                    'type' => 'number',
                                    'required' => false,
                                    'validation' => 'nullable|integer|min:1'
                                ],
                                [
                                    'field_id' => 'total_instructional_days',
                                    'label' => 'Total Instructional Days',
                                    'type' => 'number',
                                    'required' => false,
                                    'validation' => 'nullable|integer|min:1'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'step_number' => 4,
                    'title' => 'Status & Settings',
                    'sections' => [
                        [
                            'title' => 'Academic Year Status',
                            'fields' => [
                                [
                                    'field_id' => 'status',
                                    'label' => 'Status',
                                    'type' => 'select',
                                    'required' => true,
                                    'options' => [
                                        ['value' => 'planning', 'label' => 'Planning'],
                                        ['value' => 'active', 'label' => 'Active'],
                                        ['value' => 'completed', 'label' => 'Completed'],
                                        ['value' => 'archived', 'label' => 'Archived']
                                    ],
                                    'validation' => 'required|in:planning,active,completed,archived'
                                ],
                                [
                                    'field_id' => 'is_current',
                                    'label' => 'Set as Current Academic Year',
                                    'type' => 'checkbox',
                                    'required' => false,
                                    'validation' => 'boolean'
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
                'auto_save_interval' => 60
            ]
        ]);
    }

    private function createDocumentUploadForm(Tenant $tenant)
    {
        FormTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Document Upload Form',
            'description' => 'Form for uploading and managing student documents',
            'category' => 'document_upload',
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
                    'title' => 'Document Information',
                    'sections' => [
                        [
                            'title' => 'Document Details',
                            'fields' => [
                                [
                                    'field_id' => 'document_name',
                                    'label' => 'Document Name',
                                    'type' => 'text',
                                    'required' => true,
                                    'validation' => 'required|string|max:255'
                                ],
                                [
                                    'field_id' => 'document_type',
                                    'label' => 'Document Type',
                                    'type' => 'select',
                                    'required' => true,
                                    'options' => [
                                        ['value' => 'birth_certificate', 'label' => 'Birth Certificate'],
                                        ['value' => 'vaccination_records', 'label' => 'Vaccination Records'],
                                        ['value' => 'previous_transcripts', 'label' => 'Previous Transcripts'],
                                        ['value' => 'identification', 'label' => 'Identification'],
                                        ['value' => 'medical_records', 'label' => 'Medical Records'],
                                        ['value' => 'special_education', 'label' => 'Special Education'],
                                        ['value' => 'enrollment_form', 'label' => 'Enrollment Form'],
                                        ['value' => 'emergency_contacts', 'label' => 'Emergency Contacts'],
                                        ['value' => 'photo_permission', 'label' => 'Photo Permission'],
                                        ['value' => 'other', 'label' => 'Other']
                                    ],
                                    'validation' => 'required|in:birth_certificate,vaccination_records,previous_transcripts,identification,medical_records,special_education,enrollment_form,emergency_contacts,photo_permission,other'
                                ],
                                [
                                    'field_id' => 'document_category',
                                    'label' => 'Document Category',
                                    'type' => 'text',
                                    'required' => false,
                                    'validation' => 'nullable|string|max:100'
                                ],
                                [
                                    'field_id' => 'expiration_date',
                                    'label' => 'Expiration Date',
                                    'type' => 'date',
                                    'required' => false,
                                    'validation' => 'nullable|date|after:today'
                                ],
                                [
                                    'field_id' => 'required',
                                    'label' => 'Required Document',
                                    'type' => 'checkbox',
                                    'required' => false,
                                    'validation' => 'boolean'
                                ],
                                [
                                    'field_id' => 'ferpa_protected',
                                    'label' => 'FERPA Protected',
                                    'type' => 'checkbox',
                                    'required' => false,
                                    'validation' => 'boolean'
                                ],
                                [
                                    'field_id' => 'verification_notes',
                                    'label' => 'Verification Notes',
                                    'type' => 'textarea',
                                    'required' => false,
                                    'validation' => 'nullable|string|max:1000'
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
            ],
            'validation_rules' => [
                'document_name' => 'required|string|max:255',
                'document_type' => 'required|in:birth_certificate,vaccination_records,previous_transcripts,identification,medical_records,special_education,enrollment_form,emergency_contacts,photo_permission,other',
                'document_category' => 'nullable|string|max:100',
                'expiration_date' => 'nullable|date|after:today',
                'required' => 'boolean',
                'ferpa_protected' => 'boolean',
                'verification_notes' => 'nullable|string|max:1000'
            ]
        ]);
    }
}
