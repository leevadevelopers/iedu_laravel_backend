<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use App\Models\V1\SIS\Student\Student;
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

class StudentEnrollmentController extends Controller
{
    protected $formEngineService;
    protected $workflowService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
    }

    /**
     * Display a listing of enrollment records with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = StudentEnrollmentHistory::with([
                'student:id,first_name,last_name,student_number,grade_level',
                'changedBy:id,name',
                'school:id,name',
                'academicYear:id,name',
                'academicTerm:id,name'
            ]);

            // Apply filters
            if ($request->has('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('school_id')) {
                $query->where('school_id', $request->school_id);
            }

            if ($request->has('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            if ($request->has('academic_term_id')) {
                $query->where('academic_term_id', $request->academic_term_id);
            }

            if ($request->has('date_from')) {
                $query->where('changed_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('changed_at', '<=', $request->date_to);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('student', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_number', 'like', "%{$search}%");
                });
            }

            $enrollments = $query->orderBy('changed_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $enrollments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enrollment records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new enrollment record with Form Engine processing
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'status' => 'required|in:enrolled,active,inactive,transferred,graduated,suspended,withdrawn',
            'school_id' => 'required|exists:schools,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'academic_term_id' => 'nullable|exists:academic_terms,id',
            'grade_level' => 'required|string|max:10',
            'enrollment_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
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

            // Create enrollment record
            $enrollmentData = $request->except(['form_data']);
            $enrollmentData['changed_by'] = Auth::id();
            $enrollmentData['changed_at'] = now();
            $enrollmentData['tenant_id'] = Auth::user()->current_tenant_id;

            $enrollment = StudentEnrollmentHistory::create($enrollmentData);

            // Update student's current enrollment info
            $student = Student::find($request->student_id);
            $student->update([
                'status' => $request->status,
                'school_id' => $request->school_id,
                'academic_year_id' => $request->academic_year_id,
                'academic_term_id' => $request->academic_term_id,
                'grade_level' => $request->grade_level,
                'enrollment_date' => $request->enrollment_date
            ]);

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('student_enrollment', $request->form_data);
                $this->formEngineService->createFormInstance('student_enrollment', $processedData, 'StudentEnrollmentHistory', $enrollment->id);
            }

            // Start enrollment workflow
            $workflow = $this->workflowService->startWorkflow($enrollment, 'enrollment_processing', [
                'steps' => [
                    'document_verification',
                    'academic_assessment',
                    'parent_consent',
                    'final_approval'
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollment record created successfully',
                'data' => [
                    'enrollment' => $enrollment->load(['student', 'school', 'academicYear']),
                    'workflow_id' => $workflow->id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified enrollment record
     */
    public function show(StudentEnrollmentHistory $enrollment): JsonResponse
    {
        try {
            $enrollment->load([
                'student:id,first_name,last_name,student_number,grade_level,email',
                'changedBy:id,name',
                'school:id,name,address',
                'academicYear:id,name,start_date,end_date',
                'academicTerm:id,name,start_date,end_date'
            ]);

            return response()->json([
                'success' => true,
                'data' => $enrollment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified enrollment record
     */
    public function update(Request $request, StudentEnrollmentHistory $enrollment): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|required|in:enrolled,active,inactive,transferred,graduated,suspended,withdrawn',
            'grade_level' => 'sometimes|required|string|max:10',
            'end_date' => 'nullable|date|after:start_date',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
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

            $enrollment->update($request->all());

            // If status changed, update student's current status
            if ($request->has('status') && $request->status !== $enrollment->getOriginal('status')) {
                $student = $enrollment->student;
                $student->update(['status' => $request->status]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollment record updated successfully',
                'data' => $enrollment->fresh()->load(['student', 'school', 'academicYear'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified enrollment record
     */
    public function destroy(StudentEnrollmentHistory $enrollment): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Soft delete enrollment record
            $enrollment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollment record deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enrollment records by student
     */
    public function getByStudent(int $studentId): JsonResponse
    {
        try {
            $enrollments = StudentEnrollmentHistory::where('student_id', $studentId)
                ->with(['school:id,name', 'academicYear:id,name', 'academicTerm:id,name', 'changedBy:id,name'])
                ->orderBy('changed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $enrollments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student enrollment records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current enrollment status for a student
     */
    public function getCurrentEnrollment(int $studentId): JsonResponse
    {
        try {
            $currentEnrollment = StudentEnrollmentHistory::where('student_id', $studentId)
                ->with(['school:id,name', 'academicYear:id,name', 'academicTerm:id,name'])
                ->latest('changed_at')
                ->first();

            if (!$currentEnrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No enrollment record found for this student'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $currentEnrollment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current enrollment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk enroll students
     */
    public function bulkEnroll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'school_id' => 'required|exists:schools,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'academic_term_id' => 'nullable|exists:academic_terms,id',
            'grade_level' => 'required|string|max:10',
            'enrollment_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'reason' => 'nullable|string|max:500',
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
            $enrolledCount = 0;

            foreach ($students as $student) {
                // Create enrollment record
                StudentEnrollmentHistory::create([
                    'student_id' => $student->id,
                    'status' => 'enrolled',
                    'school_id' => $request->school_id,
                    'academic_year_id' => $request->academic_year_id,
                    'academic_term_id' => $request->academic_term_id,
                    'grade_level' => $request->grade_level,
                    'enrollment_date' => $request->enrollment_date,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'reason' => $request->reason,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                    'tenant_id' => $student->tenant_id
                ]);

                // Update student's current enrollment info
                $student->update([
                    'status' => 'enrolled',
                    'school_id' => $request->school_id,
                    'academic_year_id' => $request->academic_year_id,
                    'academic_term_id' => $request->academic_term_id,
                    'grade_level' => $request->grade_level,
                    'enrollment_date' => $request->enrollment_date
                ]);

                $enrolledCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully enrolled {$enrolledCount} students",
                'data' => [
                    'enrolled_count' => $enrolledCount,
                    'school_id' => $request->school_id,
                    'academic_year_id' => $request->academic_year_id,
                    'grade_level' => $request->grade_level
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk enroll students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer multiple students
     */
    public function bulkTransfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'new_school_id' => 'required|exists:schools,id',
            'transfer_date' => 'required|date|after:today',
            'reason' => 'required|string|max:500',
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
            $transferredCount = 0;

            foreach ($students as $student) {
                // Create transfer enrollment record
                StudentEnrollmentHistory::create([
                    'student_id' => $student->id,
                    'status' => 'transferred',
                    'school_id' => $request->new_school_id,
                    'academic_year_id' => $student->academic_year_id,
                    'academic_term_id' => $student->academic_term_id,
                    'grade_level' => $student->grade_level,
                    'enrollment_date' => $request->transfer_date,
                    'start_date' => $request->transfer_date,
                    'reason' => $request->reason,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                    'tenant_id' => $student->tenant_id
                ]);

                // Update student's current status and school
                $student->update([
                    'status' => 'transferred',
                    'school_id' => $request->new_school_id,
                    'transfer_date' => $request->transfer_date
                ]);

                $transferredCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully transferred {$transferredCount} students",
                'data' => [
                    'transferred_count' => $transferredCount,
                    'new_school_id' => $request->new_school_id,
                    'transfer_date' => $request->transfer_date
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enrollment statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_enrollments' => StudentEnrollmentHistory::count(),
                'by_status' => StudentEnrollmentHistory::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'by_school' => StudentEnrollmentHistory::selectRaw('school_id, COUNT(*) as count')
                    ->with('school:id,name')
                    ->groupBy('school_id')
                    ->get(),
                'by_academic_year' => StudentEnrollmentHistory::selectRaw('academic_year_id, COUNT(*) as count')
                    ->with('academicYear:id,name')
                    ->groupBy('academic_year_id')
                    ->get(),
                'recent_enrollments' => StudentEnrollmentHistory::where('changed_at', '>=', now()->subDays(30))
                    ->count(),
                'pending_transfers' => StudentEnrollmentHistory::where('status', 'transferred')
                    ->where('changed_at', '>=', now()->subDays(7))
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
     * Get enrollment trends over time
     */
    public function getEnrollmentTrends(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'monthly'); // daily, weekly, monthly, yearly
            $dateFrom = $request->get('date_from', now()->subYear());
            $dateTo = $request->get('date_to', now());

            $query = StudentEnrollmentHistory::selectRaw('DATE(changed_at) as date, COUNT(*) as count')
                ->whereBetween('changed_at', [$dateFrom, $dateTo])
                ->groupBy('date')
                ->orderBy('date');

            if ($period === 'weekly') {
                $query = StudentEnrollmentHistory::selectRaw('YEARWEEK(changed_at) as week, COUNT(*) as count')
                    ->whereBetween('changed_at', [$dateFrom, $dateTo])
                    ->groupBy('week')
                    ->orderBy('week');
            } elseif ($period === 'monthly') {
                $query = StudentEnrollmentHistory::selectRaw('DATE_FORMAT(changed_at, "%Y-%m") as month, COUNT(*) as count')
                    ->whereBetween('changed_at', [$dateFrom, $dateTo])
                    ->groupBy('month')
                    ->orderBy('month');
            } elseif ($period === 'yearly') {
                $query = StudentEnrollmentHistory::selectRaw('YEAR(changed_at) as year, COUNT(*) as count')
                    ->whereBetween('changed_at', [$dateFrom, $dateTo])
                    ->groupBy('year')
                    ->orderBy('year');
            }

            $trends = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'trends' => $trends
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get enrollment trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
