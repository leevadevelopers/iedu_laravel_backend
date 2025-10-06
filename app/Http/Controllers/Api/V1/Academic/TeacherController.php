<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\School\SchoolUser;
use App\Http\Requests\Academic\StoreTeacherRequest;
use App\Http\Requests\Academic\UpdateTeacherRequest;
use App\Services\V1\Academic\TeacherService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    use ApiResponseTrait;
    protected TeacherService $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    /**
     * Display a listing of teachers
     */
    public function index(Request $request): JsonResponse
    {
        $teachers = $this->teacherService->getTeachers($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $teachers->items(),
            'meta' => [
                'total' => $teachers->total(),
                'per_page' => $teachers->perPage(),
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created teacher
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $teacher = $this->teacherService->createTeacher($request->validated());

            // Create SchoolUser association
            if ($teacher->user_id && $teacher->school_id) {
                SchoolUser::create([
                    'school_id' => $teacher->school_id,
                    'user_id' => $teacher->user_id,
                    'role' => 'teacher',
                    'status' => 'active',
                    'start_date' => now(),
                    'permissions' => $this->getDefaultTeacherPermissions()
                ]);
            }

            DB::commit();

            return $this->successResponse($teacher, 'Teacher created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified teacher
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Find teacher explicitly to avoid model binding issues with TenantScope
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $teacher->load(['user', 'classes.subject', 'classes.academicYear'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified teacher
     */
    public function update(UpdateTeacherRequest $request, $id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $updatedTeacher = $this->teacherService->updateTeacher($teacher, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher updated successfully',
                'data' => $updatedTeacher
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified teacher
     */
    public function destroy($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $this->teacherService->deleteTeacher($teacher);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher terminated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to terminate teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get teachers by department
     */
    public function byDepartment(Request $request): JsonResponse
    {
        $request->validate([
            'department' => 'required|string|max:100'
        ]);

        $teachers = $this->teacherService->getTeachersByDepartment($request->department);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teachers by employment type
     */
    public function byEmploymentType(Request $request): JsonResponse
    {
        $request->validate([
            'employment_type' => 'required|in:full_time,part_time,substitute,contract,volunteer'
        ]);

        $teachers = $this->teacherService->getTeachersByEmploymentType($request->employment_type);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teachers by specialization
     */
    public function bySpecialization(Request $request): JsonResponse
    {
        $request->validate([
            'specialization' => 'required|string'
        ]);

        $teachers = $this->teacherService->getTeachersBySpecialization($request->specialization);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teachers by grade level
     */
    public function byGradeLevel(Request $request): JsonResponse
    {
        $request->validate([
            'grade_level' => 'required|string'
        ]);

        $teachers = $this->teacherService->getTeachersByGradeLevel($request->grade_level);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Search teachers
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2'
        ]);

        $teachers = $this->teacherService->searchTeachers($request->search);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teacher workload
     */
    public function workload($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $workload = $this->teacherService->getTeacherWorkload($teacher);

            return $this->successResponse($workload);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher workload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher's classes
     */
    public function classes($id, Request $request): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $classes = $this->teacherService->getTeacherClasses($teacher, $request->all());

            return $this->successResponse($classes);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher statistics
     */
    public function statistics($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $statistics = $this->teacherService->getTeacherStatistics($teacher);

            return $this->successResponse($statistics);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update teacher schedule
     */
    public function updateSchedule($id, Request $request): JsonResponse
    {
        $request->validate([
            'schedule' => 'required|array',
            'schedule.*' => 'array',
            'schedule.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedule.*.available_times' => 'required|array',
            'schedule.*.available_times.*' => [
                'required',
                'string',
                'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9](-[01]?[0-9]|2[0-3]:[0-5][0-9])?$/'
            ],
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $updatedTeacher = $this->teacherService->updateTeacherSchedule($teacher, $request->schedule);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher schedule updated successfully',
                'data' => $updatedTeacher
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update teacher schedule',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Check teacher availability
     */
    public function checkAvailability($id, Request $request): JsonResponse
    {
        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => ['required', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'],
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $isAvailable = $this->teacherService->checkTeacherAvailability(
                $teacher,
                $request->day,
                $request->time
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'teacher_id' => $teacher->id,
                    'day' => $request->day,
                    'time' => $request->time,
                    'available' => $isAvailable
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check teacher availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available teachers at specific time
     */
    public function availableAt(Request $request): JsonResponse
    {
        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

        $teachers = $this->teacherService->getAvailableTeachers($request->day, $request->time);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Assign teacher to class
     */
    public function assignToClass($id, Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id'
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $this->teacherService->assignTeacherToClass($teacher, $request->class_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher assigned to class successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign teacher to class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get teacher performance metrics
     */
    public function performanceMetrics($id, Request $request): JsonResponse
    {
        $request->validate([
            'academic_term_id' => 'required|exists:academic_terms,id'
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $metrics = $this->teacherService->getTeacherPerformanceMetrics(
                $teacher,
                $request->academic_term_id
            );

            return $this->successResponse($metrics);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher dashboard data
     */
    public function dashboard($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $workload = $this->teacherService->getTeacherWorkload($teacher);
            $classes = $this->teacherService->getTeacherClasses($teacher);
            $statistics = $this->teacherService->getTeacherStatistics($teacher);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'teacher' => $teacher,
                    'workload' => $workload,
                    'classes' => $classes,
                    'statistics' => $statistics
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teachers for class assignment
     */
    public function forClassAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'grade_level' => 'required|string'
        ]);

        // Get teachers by specialization and grade level separately
        $specializationTeachers = $this->teacherService->getTeachersBySpecialization($request->subject_id);
        $gradeLevelTeachers = $this->teacherService->getTeachersByGradeLevel($request->grade_level);

        // Combine and deduplicate the results
        $allTeachers = $specializationTeachers->getCollection()
            ->merge($gradeLevelTeachers->getCollection())
            ->unique('id');

        // Create a new paginator with the combined results
        $combinedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $allTeachers,
            $allTeachers->count(),
            $specializationTeachers->perPage(),
            $specializationTeachers->currentPage(),
            [
                'path' => $specializationTeachers->path(),
                'pageName' => 'page',
            ]
        );

        return $this->successPaginatedResponse($combinedPaginator);
    }

    /**
     * Get default permissions for teachers
     */
    private function getDefaultTeacherPermissions(): array
    {
        return [
            'view_students',
            'view_classes',
            'view_grades',
            'create_grades',
            'update_grades',
            'view_attendance',
            'create_attendance',
            'update_attendance',
            'view_schedule',
            'view_assignments',
            'create_assignments',
            'update_assignments',
            'view_announcements',
            'create_announcements'
        ];
    }
}
