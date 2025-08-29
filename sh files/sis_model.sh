#!/bin/bash

# Student Information System (SIS) - API Routes
# This script creates the API routes for the Student Information System

# Validate Laravel root
if [ ! -d "vendor" ]; then
    echo "❌ Error: Please run this script from the Laravel root directory (where vendor folder exists)"
    exit 1
fi

echo "🏗️ Creating SIS API routes..."

# Create or update routes/api.php
cat >> "routes/api.php" << 'EOF'

/*
|--------------------------------------------------------------------------
| Student Information System (SIS) Routes
|--------------------------------------------------------------------------
|
| These routes handle the Student Information System functionality including
| student management, family relationships, and educational workflows.
| All routes are protected with auth:api middleware and school context.
|
*/

use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\FamilyRelationshipController;

Route::prefix('v1')->middleware(['auth:api', 'school.context'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Student Management Routes
    |--------------------------------------------------------------------------
    */
    
    // Core Student CRUD Operations
    Route::apiResource('students', StudentController::class)->parameters([
        'students' => 'student:student_number'
    ]);
    
    // Additional Student Operations
    Route::prefix('students')->group(function () {
        // Academic summary for a specific student
        Route::get('{student:student_number}/academic-summary', [StudentController::class, 'academicSummary'])
            ->name('students.academic-summary');
        
        // Transfer student to another school
        Route::post('{student:student_number}/transfer', [StudentController::class, 'transfer'])
            ->name('students.transfer');
    });
    
    // Bulk Student Operations
    Route::prefix('students/bulk')->group(function () {
        // Promote multiple students to next grade level
        Route::post('promote', [StudentController::class, 'bulkPromote'])
            ->name('students.bulk.promote');
    });
    
    // Student Analytics and Reporting
    Route::prefix('students/analytics')->group(function () {
        // Get enrollment statistics by grade, status, etc.
        Route::get('enrollment-stats', [StudentController::class, 'enrollmentStats'])
            ->name('students.analytics.enrollment-stats');
        
        // Get students requiring attention (missing docs, low attendance, etc.)
        Route::get('requires-attention', [StudentController::class, 'requiresAttention'])
            ->name('students.analytics.requires-attention');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Family Relationship Routes
    |--------------------------------------------------------------------------
    */
    
    // Core Family Relationship CRUD
    Route::apiResource('family-relationships', FamilyRelationshipController::class);
    
    // Family Relationship Queries
    Route::prefix('family-relationships')->group(function () {
        // Get all relationships for a specific student
        Route::get('student/{student}', [FamilyRelationshipController::class, 'index'])
            ->name('family-relationships.by-student');
        
        // Get emergency contacts for a student
        Route::get('emergency-contacts', [FamilyRelationshipController::class, 'emergencyContacts'])
            ->name('family-relationships.emergency-contacts');
        
        // Get family summary for a student
        Route::get('family-summary', [FamilyRelationshipController::class, 'familySummary'])
            ->name('family-relationships.family-summary');
        
        // Set primary contact
        Route::patch('{familyRelationship}/set-primary', [FamilyRelationshipController::class, 'setPrimary'])
            ->name('family-relationships.set-primary');
        
        // Update specific permission
        Route::patch('{familyRelationship}/permission', [FamilyRelationshipController::class, 'updatePermission'])
            ->name('family-relationships.update-permission');
        
        // Check authorization for student access
        Route::post('check-authorization', [FamilyRelationshipController::class, 'checkAuthorization'])
            ->name('family-relationships.check-authorization');
    });
    
    // Parent Portal Routes (for guardian access)
    Route::prefix('parent-portal')->middleware('role:parent')->group(function () {
        // Get all students for the authenticated guardian
        Route::get('my-students', [FamilyRelationshipController::class, 'guardianStudents'])
            ->name('parent-portal.my-students');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Student Documents Routes (if implemented)
    |--------------------------------------------------------------------------
    */
    
    // Route::prefix('student-documents')->group(function () {
    //     Route::apiResource('', StudentDocumentController::class);
    //     Route::post('{document}/verify', [StudentDocumentController::class, 'verify'])
    //         ->name('student-documents.verify');
    //     Route::get('{document}/download', [StudentDocumentController::class, 'download'])
    //         ->name('student-documents.download');
    // });
    
});

/*
|--------------------------------------------------------------------------
| Form Engine Integration Routes
|--------------------------------------------------------------------------
|
| These routes integrate the Student Information System with the dynamic
| form engine for flexible student enrollment and data collection.
|
*/

Route::prefix('v1/forms')->middleware(['auth:api', 'school.context'])->group(function () {
    
    // Student enrollment forms
    Route::prefix('student-enrollment')->group(function () {
        // Get available enrollment form templates
        Route::get('templates', function () {
            return response()->json([
                'data' => [
                    [
                        'id' => 'student_enrollment_basic',
                        'name' => 'Basic Student Enrollment',
                        'description' => 'Standard student enrollment form with essential information',
                        'category' => 'enrollment',
                        'estimated_time' => '15 minutes'
                    ],
                    [
                        'id' => 'student_enrollment_comprehensive',
                        'name' => 'Comprehensive Student Enrollment',
                        'description' => 'Detailed enrollment form including medical and family information',
                        'category' => 'enrollment',
                        'estimated_time' => '30 minutes'
                    ],
                    [
                        'id' => 'student_transfer_in',
                        'name' => 'Transfer Student Enrollment',
                        'description' => 'Enrollment form for students transferring from other schools',
                        'category' => 'transfer',
                        'estimated_time' => '20 minutes'
                    ]
                ]
            ]);
        })->name('forms.student-enrollment.templates');
        
        // Get specific form template configuration
        Route::get('templates/{template_id}', function (string $templateId) {
            $templates = [
                'student_enrollment_basic' => [
                    'id' => 'student_enrollment_basic',
                    'name' => 'Basic Student Enrollment',
                    'version' => '1.0',
                    'steps' => [
                        [
                            'id' => 'student_information',
                            'title' => 'Student Information',
                            'fields' => [
                                [
                                    'name' => 'first_name',
                                    'type' => 'text',
                                    'label' => 'First Name',
                                    'required' => true,
                                    'validation' => 'required|string|max:100'
                                ],
                                [
                                    'name' => 'last_name',
                                    'type' => 'text',
                                    'label' => 'Last Name',
                                    'required' => true,
                                    'validation' => 'required|string|max:100'
                                ],
                                [
                                    'name' => 'middle_name',
                                    'type' => 'text',
                                    'label' => 'Middle Name',
                                    'required' => false,
                                    'validation' => 'nullable|string|max:100'
                                ],
                                [
                                    'name' => 'date_of_birth',
                                    'type' => 'date',
                                    'label' => 'Date of Birth',
                                    'required' => true,
                                    'validation' => 'required|date|before:today'
                                ],
                                [
                                    'name' => 'gender',
                                    'type' => 'select',
                                    'label' => 'Gender',
                                    'required' => false,
                                    'options' => [
                                        ['value' => 'male', 'label' => 'Male'],
                                        ['value' => 'female', 'label' => 'Female'],
                                        ['value' => 'other', 'label' => 'Other'],
                                        ['value' => 'prefer_not_to_say', 'label' => 'Prefer not to say']
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => 'academic_information',
                            'title' => 'Academic Information',
                            'fields' => [
                                [
                                    'name' => 'current_grade_level',
                                    'type' => 'select',
                                    'label' => 'Grade Level',
                                    'required' => true,
                                    'options' => [
                                        ['value' => 'Pre-K', 'label' => 'Pre-Kindergarten'],
                                        ['value' => 'K', 'label' => 'Kindergarten'],
                                        ['value' => '1', 'label' => 'Grade 1'],
                                        ['value' => '2', 'label' => 'Grade 2'],
                                        ['value' => '3', 'label' => 'Grade 3'],
                                        ['value' => '4', 'label' => 'Grade 4'],
                                        ['value' => '5', 'label' => 'Grade 5'],
                                        ['value' => '6', 'label' => 'Grade 6'],
                                        ['value' => '7', 'label' => 'Grade 7'],
                                        ['value' => '8', 'label' => 'Grade 8'],
                                        ['value' => '9', 'label' => 'Grade 9'],
                                        ['value' => '10', 'label' => 'Grade 10'],
                                        ['value' => '11', 'label' => 'Grade 11'],
                                        ['value' => '12', 'label' => 'Grade 12']
                                    ]
                                ],
                                [
                                    'name' => 'admission_date',
                                    'type' => 'date',
                                    'label' => 'Admission Date',
                                    'required' => true,
                                    'validation' => 'required|date|before_or_equal:today'
                                ]
                            ]
                        ],
                        [
                            'id' => 'contact_information',
                            'title' => 'Contact Information',
                            'fields' => [
                                [
                                    'name' => 'email',
                                    'type' => 'email',
                                    'label' => 'Email Address',
                                    'required' => false,
                                    'validation' => 'nullable|email'
                                ],
                                [
                                    'name' => 'phone',
                                    'type' => 'tel',
                                    'label' => 'Phone Number',
                                    'required' => false,
                                    'validation' => 'nullable|string|max:20'
                                ],
                                [
                                    'name' => 'address_json.street',
                                    'type' => 'text',
                                    'label' => 'Street Address',
                                    'required' => false
                                ],
                                [
                                    'name' => 'address_json.city',
                                    'type' => 'text',
                                    'label' => 'City',
                                    'required' => false
                                ],
                                [
                                    'name' => 'address_json.state',
                                    'type' => 'text',
                                    'label' => 'State/Province',
                                    'required' => false
                                ],
                                [
                                    'name' => 'address_json.postal_code',
                                    'type' => 'text',
                                    'label' => 'Postal Code',
                                    'required' => false
                                ]
                            ]
                        ],
                        [
                            'id' => 'emergency_contacts',
                            'title' => 'Emergency Contacts',
                            'fields' => [
                                [
                                    'name' => 'emergency_contacts_json',
                                    'type' => 'repeater',
                                    'label' => 'Emergency Contacts',
                                    'required' => true,
                                    'min' => 1,
                                    'max' => 5,
                                    'fields' => [
                                        [
                                            'name' => 'name',
                                            'type' => 'text',
                                            'label' => 'Contact Name',
                                            'required' => true
                                        ],
                                        [
                                            'name' => 'relationship',
                                            'type' => 'text',
                                            'label' => 'Relationship',
                                            'required' => true
                                        ],
                                        [
                                            'name' => 'phone',
                                            'type' => 'tel',
                                            'label' => 'Phone Number',
                                            'required' => true
                                        ],
                                        [
                                            'name' => 'email',
                                            'type' => 'email',
                                            'label' => 'Email Address',
                                            'required' => false
                                        ],
                                        [
                                            'name' => 'is_primary',
                                            'type' => 'checkbox',
                                            'label' => 'Primary Contact',
                                            'required' => false
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'submission_endpoint' => '/api/v1/students',
                    'submission_method' => 'POST',
                    'success_message' => 'Student enrollment completed successfully!',
                    'success_redirect' => '/students/{student_id}'
                ]
            ];
            
            if (!isset($templates[$templateId])) {
                return response()->json([
                    'message' => 'Form template not found'
                ], 404);
            }
            
            return response()->json([
                'data' => $templates[$templateId]
            ]);
        })->name('forms.student-enrollment.template');
        
        // Submit enrollment form
        Route::post('submit/{template_id}', function (string $templateId) {
            // This would integrate with the StudentController@store method
            // For now, redirect to the main student creation endpoint
            return redirect()->route('students.store');
        })->name('forms.student-enrollment.submit');
    });
    
    // Family relationship forms
    Route::prefix('family-relationships')->group(function () {
        Route::get('templates', function () {
            return response()->json([
                'data' => [
                    [
                        'id' => 'add_guardian',
                        'name' => 'Add Guardian/Family Member',
                        'description' => 'Add a new guardian or family member for a student',
                        'category' => 'family'
                    ]
                ]
            ]);
        })->name('forms.family-relationships.templates');
    });
});
EOF

echo "✅ SIS API routes created successfully!"
echo "📁 Route files updated:"
echo "   - routes/api.php (SIS routes added)"
echo ""
echo "🔧 Available API endpoints:"
echo "   Student Management:"
echo "     GET    /api/v1/students"
echo "     POST   /api/v1/students"
echo "     GET    /api/v1/students/{student_number}"
echo "     PUT    /api/v1/students/{student_number}"
echo "     DELETE /api/v1/students/{student_number}"
echo "     GET    /api/v1/students/{student_number}/academic-summary"
echo "     POST   /api/v1/students/{student_number}/transfer"
echo "     POST   /api/v1/students/bulk/promote"
echo "     GET    /api/v1/students/analytics/enrollment-stats"
echo "     GET    /api/v1/students/analytics/requires-attention"
echo ""
echo "   Family Relationships:"
echo "     GET    /api/v1/family-relationships"
echo "     POST   /api/v1/family-relationships"
echo "     GET    /api/v1/family-relationships/{id}"
echo "     PUT    /api/v1/family-relationships/{id}"
echo "     DELETE /api/v1/family-relationships/{id}"
echo "     GET    /api/v1/family-relationships/student/{student}"
echo "     GET    /api/v1/family-relationships/emergency-contacts"
echo "     PATCH  /api/v1/family-relationships/{id}/set-primary"
echo ""
echo "   Form Engine Integration:"
echo "     GET    /api/v1/forms/student-enrollment/templates"
echo "     GET    /api/v1/forms/student-enrollment/templates/{id}"