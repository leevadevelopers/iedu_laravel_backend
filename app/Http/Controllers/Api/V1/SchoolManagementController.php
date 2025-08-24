<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Services\SchoolManagementService;
use App\Models\SchoolEntities\Student;
use App\Models\SchoolEntities\SchoolClass;
use App\Models\SchoolEntities\StudentParent;
use App\Models\Forms\FormTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SchoolManagementController extends Controller
{
    protected $schoolService;

    public function __construct(SchoolManagementService $schoolService)
    {
        $this->schoolService = $schoolService;
    }

    /**
     * Enroll a new student
     */
    public function enrollStudent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'enrollment_date' => 'nullable|date',
            'grade_level' => 'required|string|max:10',
            'class_id' => 'nullable|exists:school_classes,id',
            'academic_year' => 'nullable|string|max:20',
            'parent' => 'nullable|array',
            'parent.first_name' => 'required_with:parent|string|max:255',
            'parent.last_name' => 'required_with:parent|string|max:255',
            'parent.email' => 'nullable|email|max:255',
            'parent.phone' => 'nullable|string|max:20',
            'parent.address' => 'nullable|string|max:500',
            'parent.city' => 'nullable|string|max:100',
            'parent.state' => 'nullable|string|max:100',
            'parent.postal_code' => 'nullable|string|max:20',
            'parent.country' => 'nullable|string|max:100',
            'parent.occupation' => 'nullable|string|max:255',
            'parent.employer' => 'nullable|string|max:255',
            'parent.emergency_contact' => 'nullable|string|max:255',
            'parent.relationship_type' => 'nullable|in:father,mother,guardian,other',
            'parent.is_primary_contact' => 'nullable|boolean',
            'parent.can_pickup' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $studentData = $request->except('parent');
            $parentData = $request->input('parent', []);

            $student = $this->schoolService->enrollStudent($studentData, $parentData);

            return response()->json([
                'success' => true,
                'message' => 'Student enrolled successfully',
                'data' => [
                    'student' => $student,
                    'student_code' => $student->student_code
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to enroll student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new class
     */
    public function createClass(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'class_name' => 'required|string|max:255',
            'grade_level' => 'required|string|max:10',
            'academic_year' => 'nullable|string|max:20',
            'teacher_id' => 'required|exists:users,id',
            'room_number' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:100',
            'schedule' => 'nullable|array',
            'subjects' => 'nullable|array',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $class = $this->schoolService->createClass($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Class created successfully',
                'data' => [
                    'class' => $class,
                    'class_code' => $class->class_code
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create class',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign student to class
     */
    public function assignStudentToClass(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'class_id' => 'required|exists:school_classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $student = Student::findOrFail($request->student_id);
            $this->schoolService->assignStudentToClass($student, $request->class_id);

            return response()->json([
                'success' => true,
                'message' => 'Student assigned to class successfully',
                'data' => [
                    'student_id' => $student->id,
                    'class_id' => $request->class_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign student to class',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get students by class
     */
    public function getStudentsByClass(int $classId): JsonResponse
    {
        try {
            $students = $this->schoolService->getStudentsByClass($classId);

            return response()->json([
                'success' => true,
                'data' => $students
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get class statistics
     */
    public function getClassStatistics(int $classId): JsonResponse
    {
        try {
            $statistics = $this->schoolService->getClassStatistics($classId);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get class statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get student academic summary
     */
    public function getStudentAcademicSummary(int $studentId): JsonResponse
    {
        try {
            $summary = $this->schoolService->getStudentAcademicSummary($studentId);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get student summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available form templates for school management
     */
    public function getSchoolFormTemplates(): JsonResponse
    {
        try {
            $templates = FormTemplate::where('tenant_id', auth()->user()?->current_tenant_id ?? 1)
                ->whereIn('category', [
                    'student_enrollment', 'student_registration', 'attendance',
                    'grades', 'academic_records', 'behavior_incident',
                    'parent_communication', 'teacher_evaluation', 'curriculum_planning',
                    'extracurricular', 'field_trip', 'parent_meeting', 'student_health',
                    'special_education', 'discipline', 'graduation', 'scholarship'
                ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'category', 'version', 'is_multi_step']);

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get form templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get students list with pagination
     */
    public function getStudents(Request $request): JsonResponse
    {
        try {
            $query = Student::where('tenant_id', auth()->user()?->current_tenant_id ?? 1)
                ->with(['class', 'parent']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->has('class_id')) {
                $query->where('class_id', $request->class_id);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_code', 'like', "%{$search}%");
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
                'message' => 'Failed to get students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get classes list
     */
    public function getClasses(Request $request): JsonResponse
    {
        try {
            $query = SchoolClass::where('tenant_id', auth()->user()?->current_tenant_id ?? 1)
                ->with(['teacher']);

            // Apply filters
            if ($request->has('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $classes = $query->orderBy('grade_level')
                ->orderBy('class_name')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $classes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
