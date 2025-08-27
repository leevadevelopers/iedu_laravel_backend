<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use App\Models\V1\SIS\Student\StudentDocument;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    protected $formEngineService;
    protected $workflowService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
    }

    /**
     * Display a listing of students with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Student::with([
                'enrollments',
                'documents',
                'familyRelationships',
                'school',
                'currentAcademicYear'
            ]);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->has('school_id')) {
                $query->where('school_id', $request->school_id);
            }

            if ($request->has('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_number', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $students = $query->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $students
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created student with Form Engine processing
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:students,email',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'school_id' => 'required|exists:schools,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade_level' => 'required|string|max:10',
            'enrollment_date' => 'required|date',
            'status' => 'required|in:active,inactive,transferred,graduated,suspended',
            'form_data' => 'nullable|array', // For Form Engine integration
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create student
            $studentData = $request->except(['form_data', 'family_relationships']);
            $studentData['tenant_id'] = Auth::user()->current_tenant_id;
            $studentData['student_number'] = $this->generateStudentNumber();

            $student = Student::create($studentData);

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('student_registration', $request->form_data);
                $this->formEngineService->createFormInstance('student_registration', $processedData, 'Student', $student->id);
            }

            // Start enrollment workflow
            $workflow = $this->workflowService->startWorkflow($student, 'student_enrollment', [
                'steps' => [
                    'document_verification',
                    'parent_consent',
                    'medical_assessment',
                    'final_approval'
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => [
                    'student' => $student->load(['school', 'currentAcademicYear']),
                    'workflow_id' => $workflow->id,
                    'student_number' => $student->student_number
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified student
     */
    public function show(Student $student): JsonResponse
    {
        try {
            $student->load([
                'enrollments',
                'documents',
                'familyRelationships',
                'school',
                'currentAcademicYear',
                'currentAcademicTerm'
            ]);

            return response()->json([
                'success' => true,
                'data' => $student
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified student
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:students,email,' . $student->id,
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'sometimes|required|date|before:today',
            'gender' => 'sometimes|required|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'school_id' => 'sometimes|required|exists:schools,id',
            'academic_year_id' => 'sometimes|required|exists:academic_years,id',
            'grade_level' => 'sometimes|required|string|max:10',
            'status' => 'sometimes|required|in:active,inactive,transferred,graduated,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $student->update($request->all());

            // If status changed, create enrollment history
            if ($request->has('status') && $request->status !== $student->getOriginal('status')) {
                StudentEnrollmentHistory::create([
                    'student_id' => $student->id,
                    'status' => $request->status,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                    'reason' => $request->get('status_change_reason'),
                    'tenant_id' => $student->tenant_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully',
                'data' => $student->fresh()->load(['school', 'currentAcademicYear'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified student
     */
    public function destroy(Student $student): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Soft delete student
            $student->delete();

            // Archive related records
            $student->enrollments()->update(['status' => 'archived']);
            $student->documents()->update(['status' => 'archived']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get student academic summary
     */
    public function academicSummary(Student $student): JsonResponse
    {
        try {
            $summary = [
                'student' => $student->only(['id', 'first_name', 'last_name', 'student_number', 'grade_level']),
                'current_enrollment' => $student->enrollments()->latest()->first(),
                'academic_progress' => $this->getAcademicProgress($student),
                'attendance_summary' => $this->getAttendanceSummary($student),
                'documents_status' => $this->getDocumentsStatus($student),
                'family_relationships' => $student->familyRelationships()->with('relatedPerson')->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer student to another school
     */
    public function transfer(Request $request, Student $student): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_school_id' => 'required|exists:schools,id',
            'transfer_date' => 'required|date|after:today',
            'reason' => 'required|string|max:500',
            'documents_required' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update student status and school
            $student->update([
                'school_id' => $request->new_school_id,
                'status' => 'transferred',
                'transfer_date' => $request->transfer_date
            ]);

            // Create enrollment history
            StudentEnrollmentHistory::create([
                'student_id' => $student->id,
                'status' => 'transferred',
                'changed_by' => Auth::id(),
                'changed_at' => now(),
                'reason' => $request->reason,
                'tenant_id' => $student->tenant_id
            ]);

            // Start transfer workflow
            $workflow = $this->workflowService->startWorkflow($student, 'student_transfer', [
                'steps' => [
                    'document_verification',
                    'new_school_approval',
                    'records_transfer',
                    'final_confirmation'
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student transfer initiated successfully',
                'data' => [
                    'workflow_id' => $workflow->id,
                    'transfer_date' => $request->transfer_date
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk promote students to next grade level
     */
    public function bulkPromote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'new_grade_level' => 'required|string|max:10',
            'academic_year_id' => 'required|exists:academic_years,id',
            'promotion_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $students = Student::whereIn('id', $request->student_ids)->get();
            $promotedCount = 0;

            foreach ($students as $student) {
                // Update grade level and academic year
                $student->update([
                    'grade_level' => $request->new_grade_level,
                    'academic_year_id' => $request->academic_year_id
                ]);

                // Create enrollment history
                StudentEnrollmentHistory::create([
                    'student_id' => $student->id,
                    'status' => 'promoted',
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                    'reason' => "Promoted to {$request->new_grade_level}",
                    'tenant_id' => $student->tenant_id
                ]);

                $promotedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully promoted {$promotedCount} students",
                'data' => [
                    'promoted_count' => $promotedCount,
                    'new_grade_level' => $request->new_grade_level,
                    'academic_year_id' => $request->academic_year_id
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to promote students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enrollment statistics
     */
    public function enrollmentStats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_students' => Student::count(),
                'by_status' => Student::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'by_grade_level' => Student::selectRaw('grade_level, COUNT(*) as count')
                    ->groupBy('grade_level')
                    ->get(),
                'by_school' => Student::selectRaw('school_id, COUNT(*) as count')
                    ->with('school:id,name')
                    ->groupBy('school_id')
                    ->get(),
                'recent_enrollments' => Student::where('created_at', '>=', now()->subDays(30))
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get enrollment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique student number
     */
    private function generateStudentNumber(): string
    {
        $prefix = 'STU';
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}{$year}{$random}";
    }

    /**
     * Get academic progress for student
     */
    private function getAcademicProgress(Student $student): array
    {
        // Implementation for academic progress
        return [
            'current_grade' => $student->grade_level,
            'academic_year' => $student->currentAcademicYear?->name,
            'term' => $student->currentAcademicTerm?->name
        ];
    }

    /**
     * Get attendance summary for student
     */
    private function getAttendanceSummary(Student $student): array
    {
        // Implementation for attendance summary
        return [
            'total_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'attendance_rate' => 0
        ];
    }

    /**
     * Get documents status for student
     */
    private function getDocumentsStatus(Student $student): array
    {
        $documents = $student->documents()->select('document_type', 'status', 'expiry_date')->get();

        return [
            'total_documents' => $documents->count(),
            'valid_documents' => $documents->where('status', 'valid')->count(),
            'expired_documents' => $documents->where('expiry_date', '<', now())->count(),
            'missing_documents' => $this->getMissingDocuments($student)
        ];
    }

    /**
     * Get missing documents for student
     */
    private function getMissingDocuments(Student $student): array
    {
        $requiredDocuments = ['birth_certificate', 'immunization_record', 'parent_consent'];
        $existingDocuments = $student->documents()->pluck('document_type')->toArray();

        return array_diff($requiredDocuments, $existingDocuments);
    }
}
