<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\Teacher;
use App\Http\Requests\Academic\StoreTeacherRequest;
use App\Http\Requests\Academic\UpdateTeacherRequest;
use App\Http\Resources\Academic\TeacherResource;
use App\Services\V1\Academic\TeacherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
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
            'data' => TeacherResource::collection($teachers),
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
            $teacher = $this->teacherService->createTeacher($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher created successfully',
                'data' => new TeacherResource($teacher)
            ], 201);
        } catch (\Exception $e) {
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
    public function show(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        return response()->json([
            'status' => 'success',
            'data' => new TeacherResource($teacher->load(['user', 'classes.subject', 'classes.academicYear']))
        ]);
    }

    /**
     * Update the specified teacher
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        $this->authorize('update', $teacher);

        try {
            $updatedTeacher = $this->teacherService->updateTeacher($teacher, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher updated successfully',
                'data' => new TeacherResource($updatedTeacher)
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
    public function destroy(Teacher $teacher): JsonResponse
    {
        $this->authorize('delete', $teacher);

        try {
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

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
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

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
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

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
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

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
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

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Get teacher workload
     */
    public function workload(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        $workload = $this->teacherService->getTeacherWorkload($teacher);

        return response()->json([
            'status' => 'success',
            'data' => $workload
        ]);
    }

    /**
     * Get teacher's classes
     */
    public function classes(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('view', $teacher);

        $classes = $this->teacherService->getTeacherClasses($teacher, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => $classes
        ]);
    }

    /**
     * Get teacher statistics
     */
    public function statistics(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        $statistics = $this->teacherService->getTeacherStatistics($teacher);

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    /**
     * Update teacher schedule
     */
    public function updateSchedule(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('update', $teacher);

        $request->validate([
            'schedule' => 'required|array',
            'schedule.*' => 'array',
            'schedule.*.available_times' => 'array',
            'schedule.*.available_times.*' => 'string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

        try {
            $updatedTeacher = $this->teacherService->updateTeacherSchedule($teacher, $request->schedule);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher schedule updated successfully',
                'data' => new TeacherResource($updatedTeacher)
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
    public function checkAvailability(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('view', $teacher);

        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

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

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Assign teacher to class
     */
    public function assignToClass(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('update', $teacher);

        $request->validate([
            'class_id' => 'required|exists:classes,id'
        ]);

        try {
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
    public function performanceMetrics(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('view', $teacher);

        $request->validate([
            'academic_term_id' => 'required|exists:academic_terms,id'
        ]);

        $metrics = $this->teacherService->getTeacherPerformanceMetrics(
            $teacher,
            $request->academic_term_id
        );

        return response()->json([
            'status' => 'success',
            'data' => $metrics
        ]);
    }

    /**
     * Get teacher dashboard data
     */
    public function dashboard(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        $workload = $this->teacherService->getTeacherWorkload($teacher);
        $classes = $this->teacherService->getTeacherClasses($teacher);
        $statistics = $this->teacherService->getTeacherStatistics($teacher);

        return response()->json([
            'status' => 'success',
            'data' => [
                'teacher' => new TeacherResource($teacher),
                'workload' => $workload,
                'classes' => $classes,
                'statistics' => $statistics
            ]
        ]);
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

        $teachers = $this->teacherService->getTeachersBySpecialization($request->subject_id)
            ->merge($this->teacherService->getTeachersByGradeLevel($request->grade_level))
            ->unique('id');

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }
}
